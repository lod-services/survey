<?php

namespace App\Repository;

use App\Entity\Response;
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

    public function findBySurvey(int $surveyId): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.survey = :surveyId')
            ->setParameter('surveyId', $surveyId)
            ->orderBy('r.submittedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByQuestion(int $questionId): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.question = :questionId')
            ->setParameter('questionId', $questionId)
            ->orderBy('r.submittedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findBySession(string $sessionId): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.sessionId = :sessionId')
            ->setParameter('sessionId', $sessionId)
            ->orderBy('r.submittedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getResponseCountBySurvey(int $surveyId): int
    {
        return $this->createQueryBuilder('r')
            ->select('COUNT(DISTINCT r.sessionId)')
            ->andWhere('r.survey = :surveyId')
            ->setParameter('surveyId', $surveyId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getCompletionRate(int $surveyId): float
    {
        $totalQuestions = $this->getEntityManager()
            ->createQuery('SELECT COUNT(q.id) FROM App\Entity\Question q WHERE q.survey = :surveyId')
            ->setParameter('surveyId', $surveyId)
            ->getSingleScalarResult();

        $completedSessions = $this->createQueryBuilder('r')
            ->select('r.sessionId, COUNT(r.id) as responseCount')
            ->andWhere('r.survey = :surveyId')
            ->setParameter('surveyId', $surveyId)
            ->groupBy('r.sessionId')
            ->having('responseCount = :totalQuestions')
            ->setParameter('totalQuestions', $totalQuestions)
            ->getQuery()
            ->getResult();

        $totalSessions = $this->createQueryBuilder('r')
            ->select('COUNT(DISTINCT r.sessionId)')
            ->andWhere('r.survey = :surveyId')
            ->setParameter('surveyId', $surveyId)
            ->getQuery()
            ->getSingleScalarResult();

        return $totalSessions > 0 ? count($completedSessions) / $totalSessions : 0;
    }
}