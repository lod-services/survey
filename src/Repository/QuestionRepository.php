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

    public function findBySurvey(int $surveyId): array
    {
        return $this->createQueryBuilder('q')
            ->andWhere('q.survey = :surveyId')
            ->setParameter('surveyId', $surveyId)
            ->orderBy('q.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findQuestionsWithHighBiasScore(float $threshold = 0.7): array
    {
        return $this->createQueryBuilder('q')
            ->andWhere('q.aiBiasScore >= :threshold')
            ->setParameter('threshold', $threshold)
            ->orderBy('q.aiBiasScore', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function getMaxPositionForSurvey(int $surveyId): int
    {
        $result = $this->createQueryBuilder('q')
            ->select('MAX(q.position)')
            ->andWhere('q.survey = :surveyId')
            ->setParameter('surveyId', $surveyId)
            ->getQuery()
            ->getSingleScalarResult();
        
        return $result ? (int) $result : 0;
    }

    public function findQuestionsNeedingAiAnalysis(): array
    {
        return $this->createQueryBuilder('q')
            ->andWhere('q.aiAnalysis IS NULL')
            ->orWhere('q.aiBiasScore IS NULL')
            ->getQuery()
            ->getResult();
    }
}