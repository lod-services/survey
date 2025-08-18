<?php

namespace App\Controller\Api;

use App\Repository\QuestionRepository;
use App\Repository\SurveyRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/ai', name: 'api_ai_')]
class AIAssistantController extends AbstractController
{
    public function __construct(
        private SurveyRepository $surveyRepository,
        private QuestionRepository $questionRepository
    ) {}

    #[Route('/suggest-questions', name: 'suggest_questions', methods: ['POST'])]
    public function suggestQuestions(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data || !isset($data['topic'])) {
            return $this->json(['error' => 'Topic is required'], Response::HTTP_BAD_REQUEST);
        }

        $topic = $data['topic'];
        $surveyType = $data['surveyType'] ?? 'general';
        $questionCount = min((int) ($data['questionCount'] ?? 5), 10);

        // For now, return mock AI suggestions - in real implementation this would call an AI service
        $suggestions = $this->generateMockQuestionSuggestions($topic, $surveyType, $questionCount);

        return $this->json([
            'suggestions' => $suggestions,
            'metadata' => [
                'topic' => $topic,
                'surveyType' => $surveyType,
                'generatedAt' => (new \DateTime())->format('c'),
                'requestedCount' => $questionCount,
                'actualCount' => count($suggestions)
            ]
        ]);
    }

    #[Route('/analyze-bias', name: 'analyze_bias', methods: ['POST'])]
    public function analyzeBias(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data || !isset($data['text'])) {
            return $this->json(['error' => 'Text is required'], Response::HTTP_BAD_REQUEST);
        }

        $text = $data['text'];

        // Mock bias analysis - in real implementation this would call an AI service
        $analysis = $this->generateMockBiasAnalysis($text);

        return $this->json($analysis);
    }

    #[Route('/check-inclusive-language', name: 'check_inclusive_language', methods: ['POST'])]
    public function checkInclusiveLanguage(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data || !isset($data['text'])) {
            return $this->json(['error' => 'Text is required'], Response::HTTP_BAD_REQUEST);
        }

        $text = $data['text'];

        // Mock inclusive language check - in real implementation this would call an AI service
        $analysis = $this->generateMockInclusiveLanguageAnalysis($text);

        return $this->json($analysis);
    }

    #[Route('/predict-completion-rate', name: 'predict_completion_rate', methods: ['POST'])]
    public function predictCompletionRate(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data || !isset($data['surveyId'])) {
            return $this->json(['error' => 'Survey ID is required'], Response::HTTP_BAD_REQUEST);
        }

        $surveyId = $data['surveyId'];
        $survey = $this->surveyRepository->find($surveyId);

        if (!$survey) {
            return $this->json(['error' => 'Survey not found'], Response::HTTP_NOT_FOUND);
        }

        // Mock completion rate prediction - in real implementation this would use ML models
        $prediction = $this->generateMockCompletionRatePrediction($survey);

        return $this->json($prediction);
    }

    #[Route('/optimize-question-flow', name: 'optimize_question_flow', methods: ['POST'])]
    public function optimizeQuestionFlow(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data || !isset($data['surveyId'])) {
            return $this->json(['error' => 'Survey ID is required'], Response::HTTP_BAD_REQUEST);
        }

        $surveyId = $data['surveyId'];
        $survey = $this->surveyRepository->find($surveyId);

        if (!$survey) {
            return $this->json(['error' => 'Survey not found'], Response::HTTP_NOT_FOUND);
        }

        $questions = $this->questionRepository->findBySurvey($surveyId);

        // Mock flow optimization - in real implementation this would use AI algorithms
        $optimization = $this->generateMockFlowOptimization($questions);

        return $this->json($optimization);
    }

    private function generateMockQuestionSuggestions(string $topic, string $surveyType, int $count): array
    {
        $suggestions = [];
        $templates = [
            'employee_satisfaction' => [
                'How satisfied are you with your current role and responsibilities?',
                'How would you rate the communication within your team?',
                'Do you feel your contributions are recognized and valued?',
                'How satisfied are you with the work-life balance in your position?',
                'Would you recommend this company as a great place to work?'
            ],
            'customer_feedback' => [
                'How would you rate your overall experience with our service?',
                'What aspects of our service do you value most?',
                'How likely are you to recommend our service to others?',
                'What improvements would you most like to see?',
                'How easy was it to get the help you needed?'
            ],
            'general' => [
                "What are your thoughts on {$topic}?",
                "How important is {$topic} to you?",
                "What improvements would you suggest regarding {$topic}?",
                "How satisfied are you with the current state of {$topic}?",
                "What challenges do you face related to {$topic}?"
            ]
        ];

        $questionSet = $templates[$surveyType] ?? $templates['general'];
        
        for ($i = 0; $i < min($count, count($questionSet)); $i++) {
            $suggestions[] = [
                'text' => $questionSet[$i],
                'type' => $i < 2 ? 'scale' : ($i === 2 ? 'multiple_choice' : 'text'),
                'rationale' => 'This question helps gather comprehensive feedback on ' . $topic,
                'biasScore' => round(rand(0, 30) / 100, 2), // Low bias score
                'options' => $i < 2 ? [
                    'Very Dissatisfied', 'Dissatisfied', 'Neutral', 'Satisfied', 'Very Satisfied'
                ] : null
            ];
        }

        return $suggestions;
    }

    private function generateMockBiasAnalysis(string $text): array
    {
        $biasPatterns = [
            'leading' => ['don\'t you think', 'surely you', 'obviously', 'of course'],
            'loaded' => ['amazing', 'terrible', 'fantastic', 'awful'],
            'double_barrel' => [' and ', ' or '],
            'assumption' => ['when you', 'since you', 'given that']
        ];

        $score = 0;
        $issues = [];

        foreach ($biasPatterns as $type => $patterns) {
            foreach ($patterns as $pattern) {
                if (stripos($text, $pattern) !== false) {
                    $score += 0.2;
                    $issues[] = [
                        'type' => $type,
                        'phrase' => $pattern,
                        'severity' => 'medium',
                        'suggestion' => $this->getBiasSuggestion($type)
                    ];
                }
            }
        }

        return [
            'biasScore' => min(round($score, 2), 1.0),
            'level' => $score < 0.3 ? 'low' : ($score < 0.7 ? 'medium' : 'high'),
            'issues' => $issues,
            'suggestions' => array_unique(array_column($issues, 'suggestion')),
            'analyzedAt' => (new \DateTime())->format('c')
        ];
    }

    private function generateMockInclusiveLanguageAnalysis(string $text): array
    {
        $inclusivityIssues = [
            'gender' => ['guys', 'mankind', 'manpower'],
            'accessibility' => ['blind to', 'deaf to', 'lame', 'dumb'],
            'age' => ['young people', 'old folks'],
            'cultural' => ['normal', 'exotic']
        ];

        $flags = [];
        $score = 1.0;

        foreach ($inclusivityIssues as $category => $terms) {
            foreach ($terms as $term) {
                if (stripos($text, $term) !== false) {
                    $score -= 0.15;
                    $flags[] = [
                        'category' => $category,
                        'term' => $term,
                        'position' => stripos($text, $term),
                        'suggestion' => $this->getInclusiveSuggestion($term),
                        'severity' => 'medium'
                    ];
                }
            }
        }

        return [
            'inclusivityScore' => max(round($score, 2), 0),
            'level' => $score > 0.8 ? 'excellent' : ($score > 0.6 ? 'good' : 'needs_improvement'),
            'flags' => $flags,
            'recommendations' => count($flags) > 0 ? ['Consider using more inclusive language'] : ['Language appears inclusive'],
            'analyzedAt' => (new \DateTime())->format('c')
        ];
    }

    private function generateMockCompletionRatePrediction($survey): array
    {
        $questionCount = $survey->getQuestions()->count();
        $baseRate = 0.75; // Base completion rate

        // Adjust based on question count
        $lengthPenalty = max(0, ($questionCount - 5) * 0.02);
        $predictedRate = max(0.2, $baseRate - $lengthPenalty + (rand(-10, 10) / 100));

        return [
            'predictedCompletionRate' => round($predictedRate, 2),
            'confidence' => 0.85,
            'factors' => [
                'questionCount' => $questionCount,
                'estimatedTime' => $questionCount * 1.5 . ' minutes',
                'complexity' => $questionCount > 10 ? 'high' : 'medium'
            ],
            'recommendations' => $predictedRate < 0.6 ? [
                'Consider reducing the number of questions',
                'Add progress indicators',
                'Optimize question flow'
            ] : [
                'Survey length appears optimal',
                'Consider adding engagement elements'
            ],
            'predictedAt' => (new \DateTime())->format('c')
        ];
    }

    private function generateMockFlowOptimization(array $questions): array
    {
        $recommendations = [
            'Start with engaging, easy questions',
            'Place demographic questions at the end',
            'Group related questions together',
            'Avoid placing sensitive questions early'
        ];

        $optimizedOrder = [];
        foreach ($questions as $index => $question) {
            $optimizedOrder[] = [
                'questionId' => $question->getId(),
                'currentPosition' => $question->getPosition(),
                'recommendedPosition' => $index + 1,
                'rationale' => 'Optimized for engagement and completion'
            ];
        }

        return [
            'currentOrder' => array_map(fn($q) => $q->getId(), $questions),
            'optimizedOrder' => array_column($optimizedOrder, 'questionId'),
            'improvements' => [
                'estimatedCompletionIncrease' => '12%',
                'estimatedTimeReduction' => '8%'
            ],
            'recommendations' => $recommendations,
            'changes' => $optimizedOrder,
            'analyzedAt' => (new \DateTime())->format('c')
        ];
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

    private function getInclusiveSuggestion(string $term): string
    {
        $suggestions = [
            'guys' => 'team, everyone, folks',
            'mankind' => 'humanity, people',
            'manpower' => 'workforce, staff',
            'blind to' => 'unaware of, not considering',
            'deaf to' => 'not listening to, ignoring',
            'normal' => 'typical, standard',
            'exotic' => 'unique, distinctive'
        ];

        return $suggestions[$term] ?? 'Consider using more inclusive language';
    }
}