<?php

namespace App\Repository;

use App\Entity\SurveySession;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SurveySession>
 */
class SurveySessionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SurveySession::class);
    }

    public function findByToken(string $sessionToken): ?SurveySession
    {
        return $this->findOneBy(['sessionToken' => $sessionToken]);
    }

    public function findExpiredSessions(\DateTime $cutoffDate): array
    {
        return $this->createQueryBuilder('ss')
            ->where('ss.lastActivity < :cutoff')
            ->andWhere('ss.completed = false')
            ->setParameter('cutoff', $cutoffDate)
            ->getQuery()
            ->getResult();
    }

    public function getCompletionStats(int $surveyId): array
    {
        $qb = $this->createQueryBuilder('ss')
            ->select('COUNT(ss.id) as total')
            ->addSelect('SUM(CASE WHEN ss.completed = true THEN 1 ELSE 0 END) as completed')
            ->where('ss.survey = :surveyId')
            ->setParameter('surveyId', $surveyId);

        return $qb->getQuery()->getSingleResult();
    }
}