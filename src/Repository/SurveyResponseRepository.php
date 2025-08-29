<?php

namespace App\Repository;

use App\Entity\SurveyResponse;
use App\Entity\User;
use App\Entity\Survey;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SurveyResponse>
 */
class SurveyResponseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SurveyResponse::class);
    }

    public function findCompletedByUser(User $user): array
    {
        return $this->createQueryBuilder('sr')
            ->where('sr.user = :user')
            ->andWhere('sr.isCompleted = :completed')
            ->setParameter('user', $user)
            ->setParameter('completed', true)
            ->orderBy('sr.completedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function hasUserCompletedSurvey(User $user, Survey $survey): bool
    {
        return $this->createQueryBuilder('sr')
            ->select('COUNT(sr.id)')
            ->where('sr.user = :user')
            ->andWhere('sr.survey = :survey')
            ->andWhere('sr.isCompleted = :completed')
            ->setParameter('user', $user)
            ->setParameter('survey', $survey)
            ->setParameter('completed', true)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    public function findUserStreakData(User $user, \DateTimeImmutable $since = null): array
    {
        $qb = $this->createQueryBuilder('sr')
            ->select('DATE(sr.completedAt) as completion_date')
            ->where('sr.user = :user')
            ->andWhere('sr.isCompleted = :completed')
            ->setParameter('user', $user)
            ->setParameter('completed', true)
            ->groupBy('completion_date')
            ->orderBy('completion_date', 'DESC');

        if ($since) {
            $qb->andWhere('sr.completedAt >= :since')
               ->setParameter('since', $since);
        }

        return $qb->getQuery()->getResult();
    }
}