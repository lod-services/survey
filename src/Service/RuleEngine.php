<?php

namespace App\Service;

use App\Entity\Survey;
use App\Entity\SurveySession;
use App\Entity\Question;
use App\Entity\SurveyRule;
use App\Entity\Response;
use App\Entity\ResponseAudit;
use App\Repository\SurveyRuleRepository;
use App\Repository\ResponseRepository;
use App\Repository\QuestionRepository;
use App\Repository\RuleDependencyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;

class RuleEngine
{
    private const CACHE_TTL = 300; // 5 minutes
    private const MAX_RULE_DEPTH = 10; // Prevent infinite recursion

    public function __construct(
        private EntityManagerInterface $entityManager,
        private SurveyRuleRepository $ruleRepository,
        private ResponseRepository $responseRepository,
        private QuestionRepository $questionRepository,
        private RuleDependencyRepository $dependencyRepository,
        private CacheInterface $cache,
        private LoggerInterface $logger
    ) {}

    public function evaluateAndGetNextQuestion(SurveySession $session, Question $currentQuestion): ?Question
    {
        if (!$session->getSurvey()->isBranchingEnabled()) {
            return $this->getNextQuestionInOrder($currentQuestion);
        }

        try {
            $rules = $this->getActiveRulesForSurvey($session->getSurvey());
            $responses = $this->getSessionResponses($session);
            
            // Evaluate rules in priority order
            foreach ($rules as $rule) {
                $evaluationResult = $this->evaluateRule($rule, $responses, $currentQuestion);
                
                // Log evaluation for audit
                $this->logRuleEvaluation($session, $rule, $evaluationResult);
                
                if ($evaluationResult['matched']) {
                    $nextQuestion = $this->executeRuleAction($rule, $evaluationResult, $session);
                    if ($nextQuestion) {
                        return $nextQuestion;
                    }
                }
            }

            // No rules matched, proceed to next question in order
            return $this->getNextQuestionInOrder($currentQuestion);

        } catch (\Exception $e) {
            $this->logger->error('Rule evaluation failed', [
                'session_id' => $session->getId(),
                'current_question_id' => $currentQuestion->getId(),
                'error' => $e->getMessage()
            ]);

            // Fallback to sequential order on error
            return $this->getNextQuestionInOrder($currentQuestion);
        }
    }

    public function evaluateRule(SurveyRule $rule, array $responses, Question $currentQuestion): array
    {
        $condition = $rule->getConditionJson();
        $result = [
            'matched' => false,
            'reason' => '',
            'evaluatedConditions' => []
        ];

        try {
            $result['matched'] = $this->evaluateCondition($condition, $responses, $currentQuestion);
            $result['reason'] = $result['matched'] ? 'Rule conditions satisfied' : 'Rule conditions not met';
            $result['evaluatedConditions'] = $this->getEvaluatedConditions($condition, $responses);

        } catch (\Exception $e) {
            $result['matched'] = false;
            $result['reason'] = 'Evaluation error: ' . $e->getMessage();
            $this->logger->warning('Rule condition evaluation failed', [
                'rule_id' => $rule->getId(),
                'error' => $e->getMessage(),
                'condition' => $condition
            ]);
        }

        return $result;
    }

    private function evaluateCondition(array $condition, array $responses, Question $currentQuestion): bool
    {
        $operator = $condition['operator'] ?? 'and';
        $conditions = $condition['conditions'] ?? [];

        if (empty($conditions)) {
            return false;
        }

        $results = [];
        foreach ($conditions as $cond) {
            if (isset($cond['conditions'])) {
                // Nested condition group
                $results[] = $this->evaluateCondition($cond, $responses, $currentQuestion);
            } else {
                // Simple condition
                $results[] = $this->evaluateSimpleCondition($cond, $responses, $currentQuestion);
            }
        }

        return match($operator) {
            'and' => !in_array(false, $results, true),
            'or' => in_array(true, $results, true),
            'not' => !$results[0] ?? true,
            default => false
        };
    }

    private function evaluateSimpleCondition(array $condition, array $responses, Question $currentQuestion): bool
    {
        $questionId = $condition['questionId'] ?? null;
        $operator = $condition['operator'] ?? 'equals';
        $expectedValue = $condition['value'] ?? null;

        if (!$questionId) {
            return false;
        }

        // Find response for the specified question
        $response = null;
        foreach ($responses as $r) {
            if ($r->getQuestion()->getId() == $questionId) {
                $response = $r;
                break;
            }
        }

        if (!$response) {
            return false;
        }

        $actualValue = $response->getValue();

        return match($operator) {
            'equals' => $actualValue === $expectedValue,
            'not_equals' => $actualValue !== $expectedValue,
            'contains' => str_contains($actualValue, $expectedValue),
            'not_contains' => !str_contains($actualValue, $expectedValue),
            'greater_than' => is_numeric($actualValue) && is_numeric($expectedValue) && (float)$actualValue > (float)$expectedValue,
            'less_than' => is_numeric($actualValue) && is_numeric($expectedValue) && (float)$actualValue < (float)$expectedValue,
            'greater_equal' => is_numeric($actualValue) && is_numeric($expectedValue) && (float)$actualValue >= (float)$expectedValue,
            'less_equal' => is_numeric($actualValue) && is_numeric($expectedValue) && (float)$actualValue <= (float)$expectedValue,
            'in' => is_array($expectedValue) && in_array($actualValue, $expectedValue),
            'not_in' => is_array($expectedValue) && !in_array($actualValue, $expectedValue),
            'empty' => empty($actualValue),
            'not_empty' => !empty($actualValue),
            default => false
        };
    }

    private function executeRuleAction(SurveyRule $rule, array $evaluationResult, SurveySession $session): ?Question
    {
        $action = $rule->getActionJson();
        $actionType = $action['type'] ?? 'show_question';

        return match($actionType) {
            'show_question' => $this->executeShowQuestionAction($action, $session),
            'skip_to_question' => $this->executeSkipToQuestionAction($action, $session),
            'show_section' => $this->executeShowSectionAction($action, $session),
            'end_survey' => $this->executeEndSurveyAction($session),
            default => null
        };
    }

    private function executeShowQuestionAction(array $action, SurveySession $session): ?Question
    {
        $questionId = $action['questionId'] ?? null;
        if (!$questionId) {
            return null;
        }

        $question = $this->questionRepository->find($questionId);
        if ($question && $question->getSurvey() === $session->getSurvey()) {
            return $question;
        }

        return null;
    }

    private function executeSkipToQuestionAction(array $action, SurveySession $session): ?Question
    {
        return $this->executeShowQuestionAction($action, $session);
    }

    private function executeShowSectionAction(array $action, SurveySession $session): ?Question
    {
        $sectionQuestions = $action['questionIds'] ?? [];
        if (empty($sectionQuestions)) {
            return null;
        }

        // Return first question in section
        $firstQuestionId = $sectionQuestions[0];
        return $this->questionRepository->find($firstQuestionId);
    }

    private function executeEndSurveyAction(SurveySession $session): ?Question
    {
        $session->setCompleted(true);
        return null;
    }

    private function getActiveRulesForSurvey(Survey $survey): array
    {
        $cacheKey = 'survey_rules_' . $survey->getId();
        
        return $this->cache->get($cacheKey, function() use ($survey) {
            return $this->ruleRepository->findActiveBySurveyOrderedByPriority($survey->getId());
        }, self::CACHE_TTL);
    }

    private function getSessionResponses(SurveySession $session): array
    {
        return $this->responseRepository->findBySession($session->getId());
    }

    private function getNextQuestionInOrder(Question $currentQuestion): ?Question
    {
        return $this->questionRepository->createQueryBuilder('q')
            ->where('q.survey = :survey')
            ->andWhere('q.orderIndex > :currentOrder')
            ->setParameter('survey', $currentQuestion->getSurvey())
            ->setParameter('currentOrder', $currentQuestion->getOrderIndex())
            ->orderBy('q.orderIndex', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    private function logRuleEvaluation(SurveySession $session, SurveyRule $rule, array $evaluationResult): void
    {
        // Find a response to associate with the audit (use the most recent one)
        $responses = $this->getSessionResponses($session);
        if (empty($responses)) {
            return;
        }

        $lastResponse = end($responses);

        $audit = new ResponseAudit();
        $audit->setResponse($lastResponse);
        $audit->setRule($rule);
        $audit->setEvaluationResult($evaluationResult);

        $this->entityManager->persist($audit);
        $this->entityManager->flush();
    }

    private function getEvaluatedConditions(array $condition, array $responses): array
    {
        // This method would return detailed evaluation info for debugging
        // For now, just return a simplified version
        return [
            'conditionType' => $condition['operator'] ?? 'unknown',
            'conditionsCount' => count($condition['conditions'] ?? []),
            'responsesAvailable' => count($responses)
        ];
    }

    public function validateRule(SurveyRule $rule): array
    {
        $errors = [];
        $condition = $rule->getConditionJson();
        $action = $rule->getActionJson();

        // Validate condition structure
        if (empty($condition)) {
            $errors[] = 'Rule condition cannot be empty';
        } else {
            $errors = array_merge($errors, $this->validateConditionStructure($condition, $rule->getSurvey()));
        }

        // Validate action structure
        if (empty($action)) {
            $errors[] = 'Rule action cannot be empty';
        } else {
            $errors = array_merge($errors, $this->validateActionStructure($action, $rule->getSurvey()));
        }

        // Check for circular dependencies
        $circularDeps = $this->dependencyRepository->findCircularDependencies($rule->getId());
        if (!empty($circularDeps)) {
            $errors[] = 'Rule creates circular dependencies';
        }

        return $errors;
    }

    private function validateConditionStructure(array $condition, Survey $survey): array
    {
        $errors = [];
        
        $operator = $condition['operator'] ?? null;
        if (!in_array($operator, ['and', 'or', 'not'])) {
            $errors[] = 'Invalid condition operator: ' . $operator;
        }

        $conditions = $condition['conditions'] ?? [];
        if (empty($conditions)) {
            $errors[] = 'Condition must have at least one sub-condition';
        }

        foreach ($conditions as $cond) {
            if (isset($cond['questionId'])) {
                $question = $this->questionRepository->find($cond['questionId']);
                if (!$question || $question->getSurvey() !== $survey) {
                    $errors[] = 'Invalid question ID in condition: ' . $cond['questionId'];
                }
            }
        }

        return $errors;
    }

    private function validateActionStructure(array $action, Survey $survey): array
    {
        $errors = [];
        
        $actionType = $action['type'] ?? null;
        if (!in_array($actionType, ['show_question', 'skip_to_question', 'show_section', 'end_survey'])) {
            $errors[] = 'Invalid action type: ' . $actionType;
        }

        if (in_array($actionType, ['show_question', 'skip_to_question']) && isset($action['questionId'])) {
            $question = $this->questionRepository->find($action['questionId']);
            if (!$question || $question->getSurvey() !== $survey) {
                $errors[] = 'Invalid question ID in action: ' . $action['questionId'];
            }
        }

        return $errors;
    }

    public function clearRuleCache(Survey $survey): void
    {
        $cacheKey = 'survey_rules_' . $survey->getId();
        $this->cache->delete($cacheKey);
    }
}