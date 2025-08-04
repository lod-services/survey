<?php

namespace App\Repository;

use App\Entity\Question;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Question>
 */
class QuestionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Question::class);
    }

    public function findBySurveyOrderedByIndex(int $surveyId): array
    {
        return $this->createQueryBuilder('q')
            ->where('q.survey = :surveyId')
            ->setParameter('surveyId', $surveyId)
            ->orderBy('q.orderIndex', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findRuleTargetsBySurvey(int $surveyId): array
    {
        return $this->createQueryBuilder('q')
            ->where('q.survey = :surveyId')
            ->andWhere('q.ruleTarget = true')
            ->setParameter('surveyId', $surveyId)
            ->orderBy('q.orderIndex', 'ASC')
            ->getQuery()
            ->getResult();
    }
}