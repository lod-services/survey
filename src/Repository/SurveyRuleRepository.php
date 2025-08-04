<?php

namespace App\Repository;

use App\Entity\SurveyRule;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SurveyRule>
 */
class SurveyRuleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SurveyRule::class);
    }

    public function findActiveBySurveyOrderedByPriority(int $surveyId): array
    {
        return $this->createQueryBuilder('sr')
            ->where('sr.survey = :surveyId')
            ->andWhere('sr.active = true')
            ->setParameter('surveyId', $surveyId)
            ->orderBy('sr.priority', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countBySurvey(int $surveyId): int
    {
        return (int) $this->createQueryBuilder('sr')
            ->select('COUNT(sr.id)')
            ->where('sr.survey = :surveyId')
            ->setParameter('surveyId', $surveyId)
            ->getQuery()
            ->getSingleScalarResult();
    }
}