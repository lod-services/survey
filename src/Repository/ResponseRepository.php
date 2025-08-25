<?php

namespace App\Repository;

use App\Entity\Response;
use App\Entity\Survey;
use App\Entity\User;
use App\Entity\Question;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Response>
 */
class ResponseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Response::class);
    }

    /**
     * Find all responses for a survey
     */
    public function findBySurvey(Survey $survey): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.user', 'u')
            ->leftJoin('r.question', 'q')
            ->addSelect('u', 'q')
            ->andWhere('r.survey = :survey')
            ->setParameter('survey', $survey)
            ->orderBy('r.submittedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find responses by user for a survey
     */
    public function findUserResponses(Survey $survey, User $user): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.question', 'q')
            ->addSelect('q')
            ->andWhere('r.survey = :survey')
            ->andWhere('r.user = :user')
            ->setParameter('survey', $survey)
            ->setParameter('user', $user)
            ->orderBy('q.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find anonymous responses by session ID
     */
    public function findAnonymousResponses(Survey $survey, string $sessionId): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.question', 'q')
            ->addSelect('q')
            ->andWhere('r.survey = :survey')
            ->andWhere('r.sessionId = :sessionId')
            ->andWhere('r.user IS NULL')
            ->setParameter('survey', $survey)
            ->setParameter('sessionId', $sessionId)
            ->orderBy('q.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find responses for a specific question
     */
    public function findByQuestion(Question $question): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.user', 'u')
            ->addSelect('u')
            ->andWhere('r.question = :question')
            ->setParameter('question', $question)
            ->orderBy('r.submittedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count responses for a survey
     */
    public function countBySurvey(Survey $survey): int
    {
        return $this->createQueryBuilder('r')
            ->select('COUNT(DISTINCT r.user, r.sessionId)')
            ->andWhere('r.survey = :survey')
            ->setParameter('survey', $survey)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count unique respondents (registered users + anonymous sessions)
     */
    public function countUniqueRespondents(Survey $survey): int
    {
        $registeredUsers = $this->createQueryBuilder('r')
            ->select('COUNT(DISTINCT r.user)')
            ->andWhere('r.survey = :survey')
            ->andWhere('r.user IS NOT NULL')
            ->setParameter('survey', $survey)
            ->getQuery()
            ->getSingleScalarResult();

        $anonymousSessions = $this->createQueryBuilder('r')
            ->select('COUNT(DISTINCT r.sessionId)')
            ->andWhere('r.survey = :survey')
            ->andWhere('r.user IS NULL')
            ->andWhere('r.sessionId IS NOT NULL')
            ->setParameter('survey', $survey)
            ->getQuery()
            ->getSingleScalarResult();

        return $registeredUsers + $anonymousSessions;
    }

    /**
     * Get response statistics for a question
     */
    public function getQuestionStatistics(Question $question): array
    {
        $qb = $this->createQueryBuilder('r')
            ->andWhere('r.question = :question')
            ->setParameter('question', $question);

        $total = $qb->select('COUNT(r.id)')
                   ->getQuery()
                   ->getSingleScalarResult();

        $stats = ['total' => $total];

        // Get statistics based on question type
        switch ($question->getType()) {
            case Question::TYPE_RATING:
                $stats['average'] = $qb->select('AVG(r.integerValue)')
                                      ->getQuery()
                                      ->getSingleScalarResult();
                break;

            case Question::TYPE_BOOLEAN:
                $trueCount = $qb->select('COUNT(r.id)')
                               ->andWhere('r.booleanValue = :true')
                               ->setParameter('true', true)
                               ->getQuery()
                               ->getSingleScalarResult();
                $stats['true_percentage'] = $total > 0 ? ($trueCount / $total) * 100 : 0;
                break;

            case Question::TYPE_SINGLE_CHOICE:
            case Question::TYPE_MULTIPLE_CHOICE:
                $values = $qb->select('r.textValue, r.arrayValue, COUNT(r.id) as count')
                            ->groupBy('r.textValue, r.arrayValue')
                            ->getQuery()
                            ->getResult();
                $stats['value_distribution'] = $values;
                break;
        }

        return $stats;
    }

    /**
     * Find incomplete survey responses (missing required questions)
     */
    public function findIncompleteResponses(Survey $survey): array
    {
        $requiredQuestions = $survey->getQuestions()->filter(
            fn($question) => $question->isRequired() && !$question->isDeleted()
        );

        if ($requiredQuestions->isEmpty()) {
            return [];
        }

        $requiredQuestionIds = $requiredQuestions->map(fn($q) => $q->getId())->toArray();

        // Find users/sessions that haven't answered all required questions
        return $this->createQueryBuilder('r')
            ->select('COALESCE(r.user, r.sessionId) as respondent, COUNT(DISTINCT r.question) as answered_required')
            ->leftJoin('r.question', 'q')
            ->andWhere('r.survey = :survey')
            ->andWhere('q.id IN (:requiredQuestions)')
            ->andWhere('q.isRequired = :required')
            ->setParameter('survey', $survey)
            ->setParameter('requiredQuestions', $requiredQuestionIds)
            ->setParameter('required', true)
            ->groupBy('r.user', 'r.sessionId')
            ->having('answered_required < :totalRequired')
            ->setParameter('totalRequired', count($requiredQuestionIds))
            ->getQuery()
            ->getResult();
    }

    /**
     * Export responses for a survey
     */
    public function exportSurveyResponses(Survey $survey): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.user', 'u')
            ->leftJoin('r.question', 'q')
            ->addSelect('u.email as user_email, u.name as user_name')
            ->addSelect('q.text as question_text, q.type as question_type')
            ->addSelect('r.textValue, r.integerValue, r.floatValue, r.booleanValue, r.dateValue, r.arrayValue')
            ->addSelect('r.submittedAt, r.sessionId')
            ->andWhere('r.survey = :survey')
            ->setParameter('survey', $survey)
            ->orderBy('r.submittedAt', 'ASC')
            ->addOrderBy('q.sortOrder', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Delete responses older than specified date
     */
    public function deleteOldResponses(\DateTimeImmutable $before): int
    {
        return $this->createQueryBuilder('r')
            ->delete()
            ->andWhere('r.submittedAt < :before')
            ->setParameter('before', $before)
            ->getQuery()
            ->execute();
    }
}