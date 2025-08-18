<?php

namespace App\Service\AI;

use Psr\Log\LoggerInterface;

class MockAIService implements AIServiceInterface
{
    public function __construct(
        private LoggerInterface $logger
    ) {}

    public function generateQuestionSuggestions(string $topic, array $context = []): array
    {
        $this->logger->info('Generating question suggestions', ['topic' => $topic, 'context' => $context]);

        $surveyType = $context['surveyType'] ?? 'general';
        $questionCount = $context['questionCount'] ?? 5;

        $templates = $this->getQuestionTemplates();
        $questionSet = $templates[$surveyType] ?? $templates['general'];

        $suggestions = [];
        for ($i = 0; $i < min($questionCount, count($questionSet)); $i++) {
            $suggestions[] = [
                'text' => str_replace('{topic}', $topic, $questionSet[$i]['text']),
                'type' => $questionSet[$i]['type'],
                'rationale' => $questionSet[$i]['rationale'],
                'confidence' => rand(75, 95) / 100,
                'options' => $questionSet[$i]['options'] ?? null
            ];
        }

        return [
            'suggestions' => $suggestions,
            'metadata' => [
                'provider' => 'mock',
                'model' => 'mock-v1',
                'generatedAt' => (new \DateTime())->format('c'),
                'requestTokens' => strlen($topic) + array_sum(array_map('strlen', array_values($context))),
                'responseTokens' => array_sum(array_map(fn($s) => strlen($s['text']), $suggestions))
            ]
        ];
    }

    public function analyzeBias(string $text): array
    {
        $this->logger->info('Analyzing bias', ['textLength' => strlen($text)]);

        $biasPatterns = [
            'leading' => [
                'patterns' => ['don\'t you think', 'surely you', 'obviously', 'of course', 'wouldn\'t you agree'],
                'weight' => 0.3
            ],
            'loaded' => [
                'patterns' => ['amazing', 'terrible', 'fantastic', 'awful', 'ridiculous'],
                'weight' => 0.25
            ],
            'double_barrel' => [
                'patterns' => [' and ', ' or '],
                'weight' => 0.15
            ],
            'assumption' => [
                'patterns' => ['when you', 'since you', 'given that', 'because you'],
                'weight' => 0.2
            ]
        ];

        $score = 0;
        $issues = [];

        foreach ($biasPatterns as $type => $config) {
            foreach ($config['patterns'] as $pattern) {
                if (stripos($text, $pattern) !== false) {
                    $score += $config['weight'];
                    $issues[] = [
                        'type' => $type,
                        'phrase' => $pattern,
                        'position' => stripos($text, $pattern),
                        'severity' => $this->calculateSeverity($config['weight']),
                        'suggestion' => $this->getBiasSuggestion($type),
                        'confidence' => rand(80, 95) / 100
                    ];
                }
            }
        }

        return [
            'biasScore' => min(round($score, 2), 1.0),
            'level' => $this->getBiasLevel($score),
            'issues' => $issues,
            'overallSuggestion' => $this->getOverallBiasSuggestion($score),
            'confidence' => 0.87,
            'metadata' => [
                'provider' => 'mock',
                'analyzedAt' => (new \DateTime())->format('c'),
                'textLength' => strlen($text)
            ]
        ];
    }

    public function checkInclusiveLanguage(string $text): array
    {
        $this->logger->info('Checking inclusive language', ['textLength' => strlen($text)]);

        $inclusivityPatterns = [
            'gender' => [
                'terms' => ['guys', 'mankind', 'manpower', 'manmade'],
                'suggestions' => ['everyone/folks', 'humanity', 'workforce', 'synthetic/artificial']
            ],
            'accessibility' => [
                'terms' => ['blind to', 'deaf to', 'lame', 'dumb', 'crazy'],
                'suggestions' => ['unaware of', 'ignoring', 'ineffective', 'unable to speak', 'unreasonable']
            ],
            'age' => [
                'terms' => ['young people', 'old folks', 'elderly'],
                'suggestions' => ['younger adults', 'older adults', 'older adults']
            ],
            'cultural' => [
                'terms' => ['normal', 'exotic', 'foreign'],
                'suggestions' => ['typical', 'unique', 'international']
            ]
        ];

        $flags = [];
        $score = 1.0;

        foreach ($inclusivityPatterns as $category => $config) {
            foreach ($config['terms'] as $index => $term) {
                if (stripos($text, $term) !== false) {
                    $score -= 0.12;
                    $flags[] = [
                        'category' => $category,
                        'term' => $term,
                        'position' => stripos($text, $term),
                        'suggestion' => $config['suggestions'][$index] ?? 'Consider alternative phrasing',
                        'severity' => 'medium',
                        'confidence' => rand(85, 95) / 100
                    ];
                }
            }
        }

        return [
            'inclusivityScore' => max(round($score, 2), 0),
            'level' => $this->getInclusivityLevel($score),
            'flags' => $flags,
            'recommendations' => $this->getInclusivityRecommendations($flags),
            'confidence' => 0.89,
            'metadata' => [
                'provider' => 'mock',
                'analyzedAt' => (new \DateTime())->format('c'),
                'categoriesChecked' => array_keys($inclusivityPatterns)
            ]
        ];
    }

    public function predictCompletionRate(array $surveyData): array
    {
        $this->logger->info('Predicting completion rate', ['surveyId' => $surveyData['id'] ?? 'unknown']);

        $questionCount = count($surveyData['questions'] ?? []);
        $baseRate = 0.78;

        // Apply various factors
        $lengthPenalty = max(0, ($questionCount - 5) * 0.025);
        $complexityPenalty = $this->calculateComplexityPenalty($surveyData['questions'] ?? []);
        
        $predictedRate = max(0.15, $baseRate - $lengthPenalty - $complexityPenalty + (rand(-5, 5) / 100));

        return [
            'predictedCompletionRate' => round($predictedRate, 3),
            'confidence' => 0.82,
            'factors' => [
                'questionCount' => $questionCount,
                'estimatedTime' => round($questionCount * 1.8) . ' minutes',
                'complexity' => $this->getComplexityRating($complexityPenalty),
                'lengthImpact' => round($lengthPenalty * 100) . '%',
                'complexityImpact' => round($complexityPenalty * 100) . '%'
            ],
            'recommendations' => $this->getCompletionRecommendations($predictedRate, $questionCount),
            'metadata' => [
                'provider' => 'mock',
                'model' => 'completion-predictor-v2',
                'predictedAt' => (new \DateTime())->format('c')
            ]
        ];
    }

    public function optimizeQuestionFlow(array $questions): array
    {
        $this->logger->info('Optimizing question flow', ['questionCount' => count($questions)]);

        // Simple optimization: prioritize by engagement potential
        $optimized = [];
        $scores = [];

        foreach ($questions as $index => $question) {
            $engagementScore = $this->calculateEngagementScore($question);
            $scores[$index] = $engagementScore;
        }

        // Sort by engagement score (highest first)
        arsort($scores);
        
        $position = 1;
        foreach ($scores as $originalIndex => $score) {
            $optimized[] = [
                'questionId' => $questions[$originalIndex]['id'],
                'originalPosition' => $originalIndex + 1,
                'optimizedPosition' => $position++,
                'engagementScore' => $score,
                'rationale' => $this->getOptimizationRationale($score)
            ];
        }

        return [
            'optimizedFlow' => $optimized,
            'improvements' => [
                'estimatedCompletionIncrease' => rand(8, 18) . '%',
                'estimatedEngagementIncrease' => rand(12, 25) . '%',
                'estimatedTimeReduction' => rand(5, 15) . '%'
            ],
            'recommendations' => $this->getFlowRecommendations(count($questions)),
            'confidence' => 0.79,
            'metadata' => [
                'provider' => 'mock',
                'algorithm' => 'engagement-optimizer-v1',
                'optimizedAt' => (new \DateTime())->format('c')
            ]
        ];
    }

    public function filterContent(string $content): array
    {
        $this->logger->info('Filtering content', ['contentLength' => strlen($content)]);

        $flaggedTerms = ['spam', 'inappropriate', 'offensive'];
        $flags = [];

        foreach ($flaggedTerms as $term) {
            if (stripos($content, $term) !== false) {
                $flags[] = [
                    'term' => $term,
                    'category' => 'inappropriate',
                    'severity' => 'medium',
                    'position' => stripos($content, $term)
                ];
            }
        }

        return [
            'safe' => empty($flags),
            'flags' => $flags,
            'confidence' => 0.91,
            'metadata' => [
                'provider' => 'mock',
                'filteredAt' => (new \DateTime())->format('c')
            ]
        ];
    }

    public function isAvailable(): bool
    {
        return true; // Mock service is always available
    }

    private function getQuestionTemplates(): array
    {
        return [
            'employee_satisfaction' => [
                ['text' => 'How satisfied are you with your current role and responsibilities?', 'type' => 'scale', 'rationale' => 'Measures job satisfaction'],
                ['text' => 'How would you rate the communication within your team?', 'type' => 'scale', 'rationale' => 'Assesses team dynamics'],
                ['text' => 'Do you feel your contributions are recognized and valued?', 'type' => 'scale', 'rationale' => 'Measures recognition'],
                ['text' => 'What could be improved about your work environment?', 'type' => 'text', 'rationale' => 'Gathers improvement suggestions'],
                ['text' => 'Would you recommend this company as a great place to work?', 'type' => 'scale', 'rationale' => 'Measures advocacy']
            ],
            'customer_feedback' => [
                ['text' => 'How would you rate your overall experience with {topic}?', 'type' => 'scale', 'rationale' => 'Overall satisfaction metric'],
                ['text' => 'What aspects of {topic} do you value most?', 'type' => 'multiple_choice', 'rationale' => 'Identifies key value drivers'],
                ['text' => 'How likely are you to recommend {topic} to others?', 'type' => 'scale', 'rationale' => 'Net Promoter Score'],
                ['text' => 'What improvements would you most like to see?', 'type' => 'text', 'rationale' => 'Improvement opportunities'],
                ['text' => 'How easy was it to use {topic}?', 'type' => 'scale', 'rationale' => 'Usability assessment']
            ],
            'general' => [
                ['text' => 'What are your thoughts on {topic}?', 'type' => 'text', 'rationale' => 'Open-ended exploration'],
                ['text' => 'How important is {topic} to you?', 'type' => 'scale', 'rationale' => 'Importance assessment'],
                ['text' => 'What improvements would you suggest for {topic}?', 'type' => 'text', 'rationale' => 'Improvement suggestions'],
                ['text' => 'How satisfied are you with the current state of {topic}?', 'type' => 'scale', 'rationale' => 'Current satisfaction'],
                ['text' => 'What challenges do you face related to {topic}?', 'type' => 'text', 'rationale' => 'Challenge identification']
            ]
        ];
    }

    private function calculateSeverity(float $weight): string
    {
        return $weight >= 0.25 ? 'high' : ($weight >= 0.15 ? 'medium' : 'low');
    }

    private function getBiasLevel(float $score): string
    {
        return $score < 0.3 ? 'low' : ($score < 0.7 ? 'medium' : 'high');
    }

    private function getInclusivityLevel(float $score): string
    {
        return $score > 0.8 ? 'excellent' : ($score > 0.6 ? 'good' : 'needs_improvement');
    }

    private function getBiasSuggestion(string $type): string
    {
        return match($type) {
            'leading' => 'Rephrase as a neutral question without suggesting an answer',
            'loaded' => 'Use neutral language instead of emotionally charged words',
            'double_barrel' => 'Split into separate questions to avoid confusion',
            'assumption' => 'Remove assumptions and ask directly about the topic',
            default => 'Consider rephrasing for neutrality'
        };
    }

    private function getOverallBiasSuggestion(float $score): string
    {
        if ($score < 0.3) {
            return 'Your question appears to have minimal bias. Consider it ready for use.';
        } elseif ($score < 0.7) {
            return 'Your question has moderate bias. Review the suggestions to improve neutrality.';
        } else {
            return 'Your question has significant bias. Please revise before using in your survey.';
        }
    }

    private function getInclusivityRecommendations(array $flags): array
    {
        if (empty($flags)) {
            return ['Language appears inclusive and accessible'];
        }

        $recommendations = ['Consider using more inclusive language'];
        if (count($flags) > 2) {
            $recommendations[] = 'Review multiple terms for better accessibility';
        }

        return $recommendations;
    }

    private function calculateComplexityPenalty(array $questions): float
    {
        $penalty = 0;
        foreach ($questions as $question) {
            if (($question['type'] ?? '') === 'matrix') {
                $penalty += 0.02;
            }
            if (strlen($question['text'] ?? '') > 150) {
                $penalty += 0.01;
            }
        }
        return $penalty;
    }

    private function getComplexityRating(float $penalty): string
    {
        return $penalty > 0.05 ? 'high' : ($penalty > 0.02 ? 'medium' : 'low');
    }

    private function getCompletionRecommendations(float $rate, int $questionCount): array
    {
        $recommendations = [];
        
        if ($rate < 0.5) {
            $recommendations[] = 'Consider significantly reducing the number of questions';
            $recommendations[] = 'Add strong incentives for completion';
        } elseif ($rate < 0.7) {
            $recommendations[] = 'Consider reducing question complexity';
            $recommendations[] = 'Add progress indicators';
        }
        
        if ($questionCount > 15) {
            $recommendations[] = 'Survey may be too long for optimal completion rates';
        }

        if (empty($recommendations)) {
            $recommendations[] = 'Survey appears well-optimized for completion';
        }

        return $recommendations;
    }

    private function calculateEngagementScore(array $question): float
    {
        $score = 0.5; // Base score
        
        $type = $question['type'] ?? 'text';
        $typeScores = ['scale' => 0.8, 'multiple_choice' => 0.7, 'text' => 0.6];
        $score += $typeScores[$type] ?? 0.5;
        
        $textLength = strlen($question['text'] ?? '');
        if ($textLength < 100) {
            $score += 0.2;
        } elseif ($textLength > 200) {
            $score -= 0.1;
        }
        
        return min(1.0, $score);
    }

    private function getOptimizationRationale(float $score): string
    {
        if ($score > 0.8) {
            return 'High engagement potential - place early';
        } elseif ($score > 0.6) {
            return 'Moderate engagement - middle placement';
        } else {
            return 'Lower engagement - consider revision or later placement';
        }
    }

    private function getFlowRecommendations(int $questionCount): array
    {
        $recommendations = [
            'Start with engaging, easy-to-answer questions',
            'Place demographic questions at the end',
            'Group related questions together'
        ];
        
        if ($questionCount > 10) {
            $recommendations[] = 'Consider adding section breaks for longer surveys';
        }
        
        return $recommendations;
    }
}