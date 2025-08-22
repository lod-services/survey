<?php

namespace App\Service;

use App\Entity\Survey;
use App\Entity\Question;
use App\Entity\AiSuggestion;
use OpenAI\Client;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class AiQuestionGeneratorService
{
    private const CACHE_TTL = 3600; // 1 hour
    private const MAX_SUGGESTIONS = 8;
    private const MIN_SUGGESTIONS = 5;

    public function __construct(
        private Client $openaiClient,
        private CacheInterface $cache,
        private LoggerInterface $logger
    ) {
    }

    public function generateQuestions(Survey $survey): array
    {
        $objective = $survey->getObjective() ?? 'General survey';
        $targetAudience = $survey->getTargetAudience() ?? 'General audience';
        
        $cacheKey = 'ai_questions_' . md5($objective . $targetAudience);
        
        try {
            return $this->cache->get($cacheKey, function (ItemInterface $item) use ($objective, $targetAudience) {
                $item->expiresAfter(self::CACHE_TTL);
                return $this->generateQuestionsFromAi($objective, $targetAudience);
            });
        } catch (\Exception $e) {
            $this->logger->error('AI question generation failed', [
                'error' => $e->getMessage(),
                'objective' => $objective,
                'target_audience' => $targetAudience
            ]);
            
            return $this->getFallbackQuestions($objective);
        }
    }

    private function generateQuestionsFromAi(string $objective, string $targetAudience): array
    {
        $prompt = $this->buildPrompt($objective, $targetAudience);
        
        $response = $this->openaiClient->chat()->create([
            'model' => 'gpt-4',
            'messages' => [
                ['role' => 'system', 'content' => 'You are an expert survey designer who creates clear, unbiased, and effective survey questions.'],
                ['role' => 'user', 'content' => $prompt]
            ],
            'max_tokens' => 1500,
            'temperature' => 0.7,
        ]);

        $content = $response->choices[0]->message->content;
        
        return $this->parseAiResponse($content);
    }

    private function buildPrompt(string $objective, string $targetAudience): string
    {
        return "Create " . self::MIN_SUGGESTIONS . "-" . self::MAX_SUGGESTIONS . " high-quality survey questions for the following:

Objective: {$objective}
Target Audience: {$targetAudience}

Requirements:
- Questions must be clear, concise, and unbiased
- Avoid leading questions or double-barreled questions
- Use appropriate question types (text, multiple choice, scale, etc.)
- Include a mix of question types for comprehensive data collection
- Ensure questions are accessible and inclusive

Format your response as JSON with this structure:
{
  \"questions\": [
    {
      \"text\": \"Question text here\",
      \"type\": \"text|multiple_choice|scale|email|textarea\",
      \"rationale\": \"Why this question is important and effective\",
      \"confidence\": 0.95,
      \"options\": [\"Option 1\", \"Option 2\"] // only for multiple_choice
    }
  ]
}

Focus on creating questions that will provide actionable insights related to the stated objective.";
    }

    private function parseAiResponse(string $content): array
    {
        try {
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
            
            if (!isset($decoded['questions']) || !is_array($decoded['questions'])) {
                throw new \InvalidArgumentException('Invalid AI response format');
            }

            $suggestions = [];
            foreach ($decoded['questions'] as $questionData) {
                if ($this->validateQuestionData($questionData)) {
                    $suggestions[] = [
                        'text' => $questionData['text'],
                        'type' => $questionData['type'] ?? Question::TYPE_TEXT,
                        'rationale' => $questionData['rationale'] ?? '',
                        'confidence' => min(1.0, max(0.0, $questionData['confidence'] ?? 0.8)),
                        'options' => $questionData['options'] ?? null,
                        'metadata' => [
                            'ai_generated' => true,
                            'generation_time' => time(),
                            'model' => 'gpt-4'
                        ]
                    ];
                }
            }
            
            return array_slice($suggestions, 0, self::MAX_SUGGESTIONS);
            
        } catch (\Exception $e) {
            $this->logger->warning('Failed to parse AI response', [
                'error' => $e->getMessage(),
                'content' => $content
            ]);
            
            throw new \RuntimeException('Failed to parse AI response: ' . $e->getMessage());
        }
    }

    private function validateQuestionData(array $questionData): bool
    {
        if (empty($questionData['text']) || !is_string($questionData['text'])) {
            return false;
        }

        $validTypes = [
            Question::TYPE_TEXT,
            Question::TYPE_TEXTAREA,
            Question::TYPE_MULTIPLE_CHOICE,
            Question::TYPE_CHECKBOX,
            Question::TYPE_SCALE,
            Question::TYPE_EMAIL
        ];

        $type = $questionData['type'] ?? Question::TYPE_TEXT;
        if (!in_array($type, $validTypes)) {
            return false;
        }

        // Validate that multiple choice questions have options
        if ($type === Question::TYPE_MULTIPLE_CHOICE && empty($questionData['options'])) {
            return false;
        }

        return true;
    }

    private function getFallbackQuestions(string $objective): array
    {
        // Fallback questions when AI service is unavailable
        return [
            [
                'text' => 'How would you rate your overall experience?',
                'type' => Question::TYPE_SCALE,
                'rationale' => 'General satisfaction measurement (fallback)',
                'confidence' => 0.6,
                'options' => null,
                'metadata' => ['ai_generated' => false, 'fallback' => true]
            ],
            [
                'text' => 'What aspects did you find most valuable?',
                'type' => Question::TYPE_TEXTAREA,
                'rationale' => 'Open-ended feedback collection (fallback)',
                'confidence' => 0.6,
                'options' => null,
                'metadata' => ['ai_generated' => false, 'fallback' => true]
            ],
            [
                'text' => 'Would you recommend this to others?',
                'type' => Question::TYPE_MULTIPLE_CHOICE,
                'rationale' => 'Net Promoter Score indicator (fallback)',
                'confidence' => 0.6,
                'options' => ['Yes', 'No', 'Maybe'],
                'metadata' => ['ai_generated' => false, 'fallback' => true]
            ]
        ];
    }

    public function createAiSuggestions(Survey $survey, array $questionSuggestions): array
    {
        $suggestions = [];
        
        foreach ($questionSuggestions as $questionData) {
            $suggestion = new AiSuggestion();
            $suggestion->setSurvey($survey);
            $suggestion->setSuggestionType(AiSuggestion::TYPE_QUESTION);
            $suggestion->setSuggestedContent($questionData['text']);
            $suggestion->setConfidenceScore($questionData['confidence']);
            $suggestion->setRationale($questionData['rationale']);
            $suggestion->setMetadata($questionData['metadata'] ?? []);
            
            $suggestions[] = $suggestion;
        }
        
        return $suggestions;
    }
}