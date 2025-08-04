<?php

namespace App\Service;

use App\Entity\Survey;
use App\Entity\Question;
use App\Entity\SurveyRule;
use App\Repository\SurveyRepository;
use App\Repository\QuestionRepository;
use App\Repository\SurveyRuleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class SurveyManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SurveyRepository $surveyRepository,
        private QuestionRepository $questionRepository,
        private SurveyRuleRepository $surveyRuleRepository,
        private ValidatorInterface $validator
    ) {}

    public function createSurvey(string $title, ?string $description = null): Survey
    {
        $survey = new Survey();
        $survey->setTitle($title);
        $survey->setDescription($description);

        $this->entityManager->persist($survey);
        $this->entityManager->flush();

        return $survey;
    }

    public function updateSurvey(Survey $survey, array $data): Survey
    {
        if (isset($data['title'])) {
            $survey->setTitle($data['title']);
        }

        if (isset($data['description'])) {
            $survey->setDescription($data['description']);
        }

        if (isset($data['branchingEnabled'])) {
            $survey->setBranchingEnabled($data['branchingEnabled']);
        }

        $survey->setUpdatedAt(new \DateTime());

        $errors = $this->validator->validate($survey);
        if (count($errors) > 0) {
            throw new \InvalidArgumentException('Survey validation failed: ' . (string) $errors);
        }

        $this->entityManager->flush();

        return $survey;
    }

    public function addQuestion(Survey $survey, string $type, string $content, array $options = [], bool $required = true): Question
    {
        $maxOrder = $this->questionRepository->createQueryBuilder('q')
            ->select('MAX(q.orderIndex)')
            ->where('q.survey = :survey')
            ->setParameter('survey', $survey)
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        $question = new Question();
        $question->setSurvey($survey);
        $question->setType($type);
        $question->setContent($content);
        $question->setOptions($options);
        $question->setOrderIndex($maxOrder + 1);
        $question->setRequired($required);

        $errors = $this->validator->validate($question);
        if (count($errors) > 0) {
            throw new \InvalidArgumentException('Question validation failed: ' . (string) $errors);
        }

        $this->entityManager->persist($question);
        $this->entityManager->flush();

        return $question;
    }

    public function updateQuestion(Question $question, array $data): Question
    {
        if (isset($data['type'])) {
            $question->setType($data['type']);
        }

        if (isset($data['content'])) {
            $question->setContent($data['content']);
        }

        if (isset($data['options'])) {
            $question->setOptions($data['options']);
        }

        if (isset($data['required'])) {
            $question->setRequired($data['required']);
        }

        if (isset($data['ruleTarget'])) {
            $question->setRuleTarget($data['ruleTarget']);
        }

        $errors = $this->validator->validate($question);
        if (count($errors) > 0) {
            throw new \InvalidArgumentException('Question validation failed: ' . (string) $errors);
        }

        $this->entityManager->flush();

        return $question;
    }

    public function reorderQuestions(Survey $survey, array $questionIds): void
    {
        foreach ($questionIds as $index => $questionId) {
            $question = $this->questionRepository->find($questionId);
            if ($question && $question->getSurvey() === $survey) {
                $question->setOrderIndex($index + 1);
            }
        }

        $this->entityManager->flush();
    }

    public function addRule(Survey $survey, array $condition, array $action, int $priority = 1): SurveyRule
    {
        if (!$survey->isBranchingEnabled()) {
            throw new \LogicException('Cannot add rules to survey with branching disabled');
        }

        $rule = new SurveyRule();
        $rule->setSurvey($survey);
        $rule->setConditionJson($condition);
        $rule->setActionJson($action);
        $rule->setPriority($priority);

        $errors = $this->validator->validate($rule);
        if (count($errors) > 0) {
            throw new \InvalidArgumentException('Rule validation failed: ' . (string) $errors);
        }

        $this->entityManager->persist($rule);
        $this->entityManager->flush();

        return $rule;
    }

    public function validateRuleLimit(Survey $survey): bool
    {
        $ruleCount = $this->surveyRuleRepository->countBySurvey($survey->getId());
        return $ruleCount < 50; // Limit per technical specs
    }

    public function deleteSurvey(Survey $survey): void
    {
        $this->entityManager->remove($survey);
        $this->entityManager->flush();
    }

    public function deleteQuestion(Question $question): void
    {
        $survey = $question->getSurvey();
        
        // Update order indexes of remaining questions
        $remainingQuestions = $this->questionRepository->createQueryBuilder('q')
            ->where('q.survey = :survey')
            ->andWhere('q.orderIndex > :orderIndex')
            ->setParameter('survey', $survey)
            ->setParameter('orderIndex', $question->getOrderIndex())
            ->getQuery()
            ->getResult();

        foreach ($remainingQuestions as $remainingQuestion) {
            $remainingQuestion->setOrderIndex($remainingQuestion->getOrderIndex() - 1);
        }

        $this->entityManager->remove($question);
        $this->entityManager->flush();
    }

    public function getSurveyWithQuestions(int $surveyId): ?Survey
    {
        return $this->surveyRepository->createQueryBuilder('s')
            ->leftJoin('s.questions', 'q')
            ->addSelect('q')
            ->where('s.id = :surveyId')
            ->setParameter('surveyId', $surveyId)
            ->orderBy('q.orderIndex', 'ASC')
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function getSurveyStats(Survey $survey): array
    {
        $questionCount = $this->questionRepository->createQueryBuilder('q')
            ->select('COUNT(q.id)')
            ->where('q.survey = :survey')
            ->setParameter('survey', $survey)
            ->getQuery()
            ->getSingleScalarResult();

        $ruleCount = $this->surveyRuleRepository->countBySurvey($survey->getId());

        return [
            'questionCount' => (int) $questionCount,
            'ruleCount' => $ruleCount,
            'branchingEnabled' => $survey->isBranchingEnabled(),
            'canAddRules' => $this->validateRuleLimit($survey)
        ];
    }
}