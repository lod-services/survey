<?php

namespace App\Service;

use App\Entity\Survey;
use App\Entity\SurveySession;
use App\Entity\Question;
use App\Entity\Response;
use App\Repository\SurveySessionRepository;
use App\Repository\QuestionRepository;
use App\Repository\ResponseRepository;
use Doctrine\ORM\EntityManagerInterface;

class SurveySessionManager
{
    private const SESSION_TIMEOUT_HOURS = 24;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private SurveySessionRepository $sessionRepository,
        private QuestionRepository $questionRepository,
        private ResponseRepository $responseRepository,
        private RuleEngine $ruleEngine
    ) {}

    public function createSession(Survey $survey): SurveySession
    {
        $session = new SurveySession();
        $session->setSurvey($survey);

        // Set initial question (first question in order)
        $firstQuestion = $this->questionRepository->createQueryBuilder('q')
            ->where('q.survey = :survey')
            ->setParameter('survey', $survey)
            ->orderBy('q.orderIndex', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($firstQuestion) {
            $session->setCurrentQuestion($firstQuestion);
        }

        $this->entityManager->persist($session);
        $this->entityManager->flush();

        return $session;
    }

    public function getSession(string $sessionToken): ?SurveySession
    {
        $session = $this->sessionRepository->findByToken($sessionToken);
        
        if ($session && $this->isSessionExpired($session)) {
            return null;
        }

        return $session;
    }

    public function getOrCreateSession(Survey $survey, ?string $sessionToken = null): SurveySession
    {
        if ($sessionToken) {
            $session = $this->getSession($sessionToken);
            if ($session && $session->getSurvey() === $survey) {
                $session->updateActivity();
                $this->entityManager->flush();
                return $session;
            }
        }

        return $this->createSession($survey);
    }

    public function submitResponse(SurveySession $session, Question $question, string $value): Response
    {
        if ($session->isCompleted()) {
            throw new \LogicException('Cannot submit response to completed session');
        }

        // Check if response already exists for this question
        $existingResponse = $this->responseRepository->findBySessionAndQuestion(
            $session->getId(),
            $question->getId()
        );

        if ($existingResponse) {
            $existingResponse->setValue($value);
            $response = $existingResponse;
        } else {
            $response = new Response();
            $response->setSession($session);
            $response->setQuestion($question);
            $response->setValue($value);
            $this->entityManager->persist($response);
        }

        $session->updateActivity();
        $this->entityManager->flush();

        // Evaluate rules and determine next question
        if ($session->getSurvey()->isBranchingEnabled()) {
            $nextQuestion = $this->ruleEngine->evaluateAndGetNextQuestion($session, $question);
        } else {
            $nextQuestion = $this->getNextQuestionInOrder($question);
        }

        $session->setCurrentQuestion($nextQuestion);

        // Mark as completed if no more questions
        if (!$nextQuestion) {
            $session->setCompleted(true);
        }

        $this->updateSessionProgress($session);
        $this->entityManager->flush();

        return $response;
    }

    public function getNextQuestionInOrder(Question $currentQuestion): ?Question
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

    public function getPreviousQuestion(SurveySession $session): ?Question
    {
        if (!$session->getCurrentQuestion()) {
            return null;
        }

        $responses = $this->responseRepository->findBySession($session->getId());
        if (empty($responses)) {
            return null;
        }

        // Get the last response before current question
        $lastResponse = end($responses);
        return $lastResponse->getQuestion();
    }

    public function getSessionProgress(SurveySession $session): array
    {
        $survey = $session->getSurvey();
        $totalQuestions = $this->questionRepository->createQueryBuilder('q')
            ->select('COUNT(q.id)')
            ->where('q.survey = :survey')
            ->setParameter('survey', $survey)
            ->getQuery()
            ->getSingleScalarResult();

        $answeredQuestions = $this->responseRepository->createQueryBuilder('r')
            ->select('COUNT(DISTINCT r.question)')
            ->where('r.session = :session')
            ->setParameter('session', $session)
            ->getQuery()
            ->getSingleScalarResult();

        $progressPercentage = $totalQuestions > 0 ? ($answeredQuestions / $totalQuestions) * 100 : 0;

        return [
            'totalQuestions' => (int) $totalQuestions,
            'answeredQuestions' => (int) $answeredQuestions,
            'progressPercentage' => round($progressPercentage, 2),
            'isCompleted' => $session->isCompleted(),
            'currentQuestion' => $session->getCurrentQuestion()?->getId(),
            'sessionToken' => $session->getSessionToken()
        ];
    }

    private function updateSessionProgress(SurveySession $session): void
    {
        $progress = $this->getSessionProgress($session);
        $session->setProgressData($progress);
    }

    public function getSessionResponses(SurveySession $session): array
    {
        return $this->responseRepository->findBySession($session->getId());
    }

    public function isSessionExpired(SurveySession $session): bool
    {
        $expireTime = clone $session->getLastActivity();
        $expireTime->add(new \DateInterval('PT' . self::SESSION_TIMEOUT_HOURS . 'H'));
        
        return new \DateTime() > $expireTime;
    }

    public function cleanupExpiredSessions(): int
    {
        $cutoffDate = new \DateTime();
        $cutoffDate->sub(new \DateInterval('PT' . self::SESSION_TIMEOUT_HOURS . 'H'));

        $expiredSessions = $this->sessionRepository->findExpiredSessions($cutoffDate);

        foreach ($expiredSessions as $session) {
            $this->entityManager->remove($session);
        }

        $this->entityManager->flush();

        return count($expiredSessions);
    }

    public function completeSession(SurveySession $session): void
    {
        if (!$session->isCompleted()) {
            $session->setCompleted(true);
            $this->updateSessionProgress($session);
            $this->entityManager->flush();
        }
    }

    public function canGoBack(SurveySession $session): bool
    {
        $responses = $this->responseRepository->findBySession($session->getId());
        return count($responses) > 0;
    }

    public function goToPreviousQuestion(SurveySession $session): ?Question
    {
        $previousQuestion = $this->getPreviousQuestion($session);
        if ($previousQuestion) {
            $session->setCurrentQuestion($previousQuestion);
            $session->updateActivity();
            $this->entityManager->flush();
        }
        return $previousQuestion;
    }
}