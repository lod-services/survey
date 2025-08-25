<?php

namespace App\Repository;

use App\Entity\Question;
use App\Entity\Survey;
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

    /**
     * Find all questions for a survey (ordered by sort order)
     */
    public function findBySurvey(Survey $survey): array
    {
        return $this->createQueryBuilder('q')
            ->andWhere('q.survey = :survey')
            ->andWhere('q.deletedAt IS NULL')
            ->setParameter('survey', $survey)
            ->orderBy('q.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find questions with their responses
     */
    public function findQuestionsWithResponses(Survey $survey): array
    {
        return $this->createQueryBuilder('q')
            ->leftJoin('q.responses', 'r')
            ->addSelect('r')
            ->andWhere('q.survey = :survey')
            ->andWhere('q.deletedAt IS NULL')
            ->setParameter('survey', $survey)
            ->orderBy('q.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find questions by type
     */
    public function findByType(string $type, Survey $survey = null): array
    {
        $qb = $this->createQueryBuilder('q')
            ->andWhere('q.type = :type')
            ->andWhere('q.deletedAt IS NULL')
            ->setParameter('type', $type);

        if ($survey) {
            $qb->andWhere('q.survey = :survey')
               ->setParameter('survey', $survey);
        }

        return $qb->orderBy('q.sortOrder', 'ASC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Find required questions for a survey
     */
    public function findRequiredQuestions(Survey $survey): array
    {
        return $this->createQueryBuilder('q')
            ->andWhere('q.survey = :survey')
            ->andWhere('q.isRequired = :required')
            ->andWhere('q.deletedAt IS NULL')
            ->setParameter('survey', $survey)
            ->setParameter('required', true)
            ->orderBy('q.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get next sort order for a survey
     */
    public function getNextSortOrder(Survey $survey): int
    {
        $result = $this->createQueryBuilder('q')
            ->select('MAX(q.sortOrder)')
            ->andWhere('q.survey = :survey')
            ->andWhere('q.deletedAt IS NULL')
            ->setParameter('survey', $survey)
            ->getQuery()
            ->getSingleScalarResult();

        return ($result ?? 0) + 10;
    }

    /**
     * Find questions with response counts
     */
    public function findQuestionsWithResponseCounts(Survey $survey): array
    {
        return $this->createQueryBuilder('q')
            ->leftJoin('q.responses', 'r')
            ->addSelect('COUNT(r.id) as responseCount')
            ->andWhere('q.survey = :survey')
            ->andWhere('q.deletedAt IS NULL')
            ->setParameter('survey', $survey)
            ->groupBy('q.id')
            ->orderBy('q.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Reorder questions in a survey
     */
    public function reorderQuestions(Survey $survey, array $questionIds): void
    {
        $sortOrder = 10;
        foreach ($questionIds as $questionId) {
            $this->createQueryBuilder('q')
                ->update()
                ->set('q.sortOrder', ':sortOrder')
                ->andWhere('q.id = :id')
                ->andWhere('q.survey = :survey')
                ->setParameter('sortOrder', $sortOrder)
                ->setParameter('id', $questionId)
                ->setParameter('survey', $survey)
                ->getQuery()
                ->execute();
            
            $sortOrder += 10;
        }
    }

    /**
     * Count questions by type for a survey
     */
    public function countByType(Survey $survey): array
    {
        return $this->createQueryBuilder('q')
            ->select('q.type, COUNT(q.id) as count')
            ->andWhere('q.survey = :survey')
            ->andWhere('q.deletedAt IS NULL')
            ->setParameter('survey', $survey)
            ->groupBy('q.type')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find questions that allow multiple responses
     */
    public function findMultipleChoiceQuestions(Survey $survey): array
    {
        return $this->createQueryBuilder('q')
            ->andWhere('q.survey = :survey')
            ->andWhere('q.type = :type')
            ->andWhere('q.deletedAt IS NULL')
            ->setParameter('survey', $survey)
            ->setParameter('type', Question::TYPE_MULTIPLE_CHOICE)
            ->orderBy('q.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }
}