<?php

namespace App\Service;

use App\Entity\Survey;
use App\Entity\Question;
use App\Entity\AiSuggestion;
use OpenAI\Client;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class AiBiasDetectionService
{
    private const CACHE_TTL = 1800; // 30 minutes
    private const BIAS_THRESHOLD = 0.6; // Confidence threshold for bias detection

    // Common bias patterns for quick detection
    private const BIAS_PATTERNS = [
        'leading' => [
            'patterns' => ['/don\'t you think/i', '/wouldn\'t you agree/i', '/isn\'t it true/i'],
            'message' => 'This appears to be a leading question that suggests a preferred answer.'
        ],
        'double_barreled' => [
            'patterns' => ['/\s+and\s+/i', '/\s+or\s+/i'],
            'message' => 'This question may be asking about multiple topics at once (double-barreled).'
        ],
        'loaded_language' => [
            'patterns' => ['/obviously/i', '/clearly/i', '/certainly/i', '/of course/i'],
            'message' => 'This question contains loaded language that may influence responses.'
        ],
        'extreme_language' => [
            'patterns' => ['/always/i', '/never/i', '/all/i', '/none/i', '/every/i'],
            'message' => 'Extreme absolutes like "always" or "never" may not reflect real experiences.'
        ]
    ];

    public function __construct(
        private Client $openaiClient,
        private CacheInterface $cache,
        private LoggerInterface $logger
    ) {
    }

    public function analyzeBias(string $questionText): array
    {
        $cacheKey = 'bias_analysis_' . md5($questionText);
        
        try {
            return $this->cache->get($cacheKey, function (ItemInterface $item) use ($questionText) {
                $item->expiresAfter(self::CACHE_TTL);
                return $this->performBiasAnalysis($questionText);
            });
        } catch (\Exception $e) {
            $this->logger->error('Bias detection failed', [
                'error' => $e->getMessage(),
                'question' => $questionText
            ]);
            
            return $this->performBasicBiasCheck($questionText);
        }
    }

    private function performBiasAnalysis(string $questionText): array
    {
        // First, do a quick pattern-based check
        $basicCheck = $this->performBasicBiasCheck($questionText);
        
        // If basic check finds issues, combine with AI analysis
        $prompt = $this->buildBiasAnalysisPrompt($questionText);
        
        $response = $this->openaiClient->chat()->create([
            'model' => 'gpt-4',
            'messages' => [
                ['role' => 'system', 'content' => 'You are an expert in survey methodology and question design. Analyze survey questions for bias, clarity, and effectiveness.'],
                ['role' => 'user', 'content' => $prompt]
            ],
            'max_tokens' => 800,
            'temperature' => 0.3, // Lower temperature for more consistent analysis
        ]);

        $content = $response->choices[0]->message->content;
        $aiAnalysis = $this->parseAiAnalysis($content);
        
        // Combine basic check with AI analysis
        return $this->combineAnalyses($basicCheck, $aiAnalysis);
    }

    private function buildBiasAnalysisPrompt(string $questionText): string
    {
        return "Analyze this survey question for bias, clarity, and effectiveness:

Question: \"{$questionText}\"

Please evaluate for:
1. Leading questions that suggest a preferred answer
2. Double-barreled questions that ask about multiple topics
3. Loaded or emotionally charged language
4. Clarity and comprehension issues
5. Cultural or demographic bias
6. Question neutrality

Provide your analysis in JSON format:
{
  \"bias_detected\": true/false,
  \"confidence\": 0.95,
  \"bias_types\": [\"leading\", \"double_barreled\"],
  \"issues\": [
    {
      \"type\": \"leading\",
      \"description\": \"The question suggests a preferred answer\",
      \"severity\": \"high\"
    }
  ],
  \"suggestions\": [
    {
      \"improved_text\": \"Alternative question wording\",
      \"rationale\": \"Why this is better\"
    }
  ],
  \"clarity_score\": 0.85,
  \"neutrality_score\": 0.70
}";
    }

    private function parseAiAnalysis(string $content): array
    {
        try {
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
            
            return [
                'bias_detected' => $decoded['bias_detected'] ?? false,
                'confidence' => min(1.0, max(0.0, $decoded['confidence'] ?? 0.5)),
                'bias_types' => $decoded['bias_types'] ?? [],
                'issues' => $decoded['issues'] ?? [],
                'suggestions' => $decoded['suggestions'] ?? [],
                'clarity_score' => min(1.0, max(0.0, $decoded['clarity_score'] ?? 0.8)),
                'neutrality_score' => min(1.0, max(0.0, $decoded['neutrality_score'] ?? 0.8)),
                'analysis_type' => 'ai_enhanced'
            ];
            
        } catch (\Exception $e) {
            $this->logger->warning('Failed to parse AI bias analysis', [
                'error' => $e->getMessage(),
                'content' => $content
            ]);
            
            return [
                'bias_detected' => false,
                'confidence' => 0.3,
                'analysis_type' => 'ai_parse_failed',
                'error' => $e->getMessage()
            ];
        }
    }

    private function performBasicBiasCheck(string $questionText): array
    {
        $issues = [];
        $biasTypes = [];
        $hasBias = false;
        
        foreach (self::BIAS_PATTERNS as $biasType => $pattern) {
            foreach ($pattern['patterns'] as $regex) {
                if (preg_match($regex, $questionText)) {
                    $issues[] = [
                        'type' => $biasType,
                        'description' => $pattern['message'],
                        'severity' => 'medium'
                    ];
                    $biasTypes[] = $biasType;
                    $hasBias = true;
                    break;
                }
            }
        }
        
        // Additional basic checks
        $wordCount = str_word_count($questionText);
        if ($wordCount > 30) {
            $issues[] = [
                'type' => 'complexity',
                'description' => 'Question may be too long or complex for clear understanding.',
                'severity' => 'low'
            ];
        }
        
        return [
            'bias_detected' => $hasBias,
            'confidence' => $hasBias ? 0.7 : 0.8,
            'bias_types' => array_unique($biasTypes),
            'issues' => $issues,
            'suggestions' => $this->generateBasicSuggestions($questionText, $issues),
            'clarity_score' => $wordCount > 30 ? 0.6 : 0.8,
            'neutrality_score' => $hasBias ? 0.5 : 0.9,
            'analysis_type' => 'pattern_based'
        ];
    }

    private function generateBasicSuggestions(string $questionText, array $issues): array
    {
        $suggestions = [];
        
        foreach ($issues as $issue) {
            switch ($issue['type']) {
                case 'leading':
                    $suggestions[] = [
                        'improved_text' => $this->neutralizeLeadingQuestion($questionText),
                        'rationale' => 'Removed leading language to allow neutral responses'
                    ];
                    break;
                    
                case 'double_barreled':
                    $suggestions[] = [
                        'improved_text' => 'Consider splitting this into separate questions',
                        'rationale' => 'Each question should focus on a single topic for clearer responses'
                    ];
                    break;
                    
                case 'extreme_language':
                    $suggestions[] = [
                        'improved_text' => $this->softenExtremeLanguage($questionText),
                        'rationale' => 'Use less absolute language to better reflect real experiences'
                    ];
                    break;
            }
        }
        
        return $suggestions;
    }

    private function neutralizeLeadingQuestion(string $questionText): string
    {
        $neutralized = preg_replace('/don\'t you think\s*/i', '', $questionText);
        $neutralized = preg_replace('/wouldn\'t you agree\s*/i', '', $neutralized);
        $neutralized = preg_replace('/isn\'t it true\s*/i', '', $neutralized);
        
        // Convert to neutral form
        if (preg_match('/\?$/', $neutralized)) {
            return 'How would you rate ' . strtolower(trim($neutralized, '?')) . '?';
        }
        
        return $neutralized;
    }

    private function softenExtremeLanguage(string $questionText): string
    {
        $softened = preg_replace('/\balways\b/i', 'usually', $questionText);
        $softened = preg_replace('/\bnever\b/i', 'rarely', $softened);
        $softened = preg_replace('/\ball\b/i', 'most', $softened);
        $softened = preg_replace('/\bnone\b/i', 'few', $softened);
        
        return $softened;
    }

    private function combineAnalyses(array $basicCheck, array $aiAnalysis): array
    {
        // Use AI analysis as primary if available, supplement with basic check
        if ($aiAnalysis['analysis_type'] === 'ai_enhanced') {
            return array_merge($basicCheck, $aiAnalysis, [
                'combined_confidence' => ($basicCheck['confidence'] + $aiAnalysis['confidence']) / 2,
                'all_issues' => array_merge($basicCheck['issues'], $aiAnalysis['issues']),
                'all_suggestions' => array_merge($basicCheck['suggestions'], $aiAnalysis['suggestions'])
            ]);
        }
        
        return $basicCheck;
    }

    public function createBiasSuggestion(Survey $survey, string $originalQuestion, array $biasAnalysis): ?AiSuggestion
    {
        if (!$biasAnalysis['bias_detected'] || $biasAnalysis['confidence'] < self::BIAS_THRESHOLD) {
            return null;
        }

        $suggestion = new AiSuggestion();
        $suggestion->setSurvey($survey);
        $suggestion->setSuggestionType(AiSuggestion::TYPE_BIAS_DETECTION);
        $suggestion->setOriginalContent($originalQuestion);
        
        // Use the best suggestion if available
        $bestSuggestion = $biasAnalysis['suggestions'][0] ?? null;
        if ($bestSuggestion) {
            $suggestion->setSuggestedContent($bestSuggestion['improved_text']);
            $suggestion->setRationale($bestSuggestion['rationale']);
        } else {
            $suggestion->setSuggestedContent('Consider revising this question to reduce bias');
            $suggestion->setRationale('Potential bias detected: ' . implode(', ', $biasAnalysis['bias_types']));
        }
        
        $suggestion->setConfidenceScore($biasAnalysis['confidence']);
        $suggestion->setMetadata([
            'bias_types' => $biasAnalysis['bias_types'],
            'issues' => $biasAnalysis['issues'],
            'clarity_score' => $biasAnalysis['clarity_score'],
            'neutrality_score' => $biasAnalysis['neutrality_score'],
            'analysis_type' => $biasAnalysis['analysis_type']
        ]);
        
        return $suggestion;
    }

    public function shouldFlagQuestion(array $biasAnalysis): bool
    {
        return $biasAnalysis['bias_detected'] && $biasAnalysis['confidence'] >= self::BIAS_THRESHOLD;
    }
}