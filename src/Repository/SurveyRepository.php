<?php

namespace App\Repository;

use App\Entity\Survey;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Survey>
 */
class SurveyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Survey::class);
    }

    /**
     * Find all active surveys (excluding soft-deleted)
     */
    public function findActiveSurveys(): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.deletedAt IS NULL')
            ->andWhere('s.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('s.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find public surveys that are available
     */
    public function findPublicAvailableSurveys(): array
    {
        $now = new \DateTimeImmutable();
        
        return $this->createQueryBuilder('s')
            ->andWhere('s.deletedAt IS NULL')
            ->andWhere('s.isActive = :active')
            ->andWhere('s.isPublic = :public')
            ->andWhere('s.startDate IS NULL OR s.startDate <= :now')
            ->andWhere('s.endDate IS NULL OR s.endDate > :now')
            ->setParameter('active', true)
            ->setParameter('public', true)
            ->setParameter('now', $now)
            ->orderBy('s.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find surveys created by a specific user
     */
    public function findByCreator(User $creator): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.creator = :creator')
            ->andWhere('s.deletedAt IS NULL')
            ->setParameter('creator', $creator)
            ->orderBy('s.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find surveys with their questions (eager loading)
     */
    public function findSurveyWithQuestions(int $id): ?Survey
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.questions', 'q')
            ->addSelect('q')
            ->andWhere('s.id = :id')
            ->andWhere('s.deletedAt IS NULL')
            ->andWhere('q.deletedAt IS NULL')
            ->setParameter('id', $id)
            ->orderBy('q.sortOrder', 'ASC')
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find surveys with response counts
     */
    public function findSurveysWithResponseCounts(User $creator = null): array
    {
        $qb = $this->createQueryBuilder('s')
            ->leftJoin('s.responses', 'r')
            ->addSelect('COUNT(r.id) as responseCount')
            ->andWhere('s.deletedAt IS NULL')
            ->groupBy('s.id');

        if ($creator) {
            $qb->andWhere('s.creator = :creator')
               ->setParameter('creator', $creator);
        }

        return $qb->orderBy('s.createdAt', 'DESC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Search surveys by title or description
     */
    public function searchSurveys(string $query, User $creator = null): array
    {
        $qb = $this->createQueryBuilder('s')
            ->andWhere('s.deletedAt IS NULL')
            ->andWhere('s.title LIKE :query OR s.description LIKE :query')
            ->setParameter('query', '%' . $query . '%');

        if ($creator) {
            $qb->andWhere('s.creator = :creator')
               ->setParameter('creator', $creator);
        }

        return $qb->orderBy('s.createdAt', 'DESC')
                  ->setMaxResults(20)
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Find expired surveys
     */
    public function findExpiredSurveys(): array
    {
        $now = new \DateTimeImmutable();
        
        return $this->createQueryBuilder('s')
            ->andWhere('s.deletedAt IS NULL')
            ->andWhere('s.endDate IS NOT NULL')
            ->andWhere('s.endDate < :now')
            ->andWhere('s.isActive = :active')
            ->setParameter('now', $now)
            ->setParameter('active', true)
            ->getQuery()
            ->getResult();
    }

    /**
     * Count surveys by status
     */
    public function countByStatus(User $creator = null): array
    {
        $now = new \DateTimeImmutable();
        
        $qb = $this->createQueryBuilder('s')
            ->select('
                SUM(CASE WHEN s.isActive = 1 AND s.deletedAt IS NULL THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN s.isActive = 0 AND s.deletedAt IS NULL THEN 1 ELSE 0 END) as inactive,
                SUM(CASE WHEN s.deletedAt IS NOT NULL THEN 1 ELSE 0 END) as deleted,
                SUM(CASE WHEN s.endDate IS NOT NULL AND s.endDate < :now THEN 1 ELSE 0 END) as expired
            ')
            ->setParameter('now', $now);

        if ($creator) {
            $qb->andWhere('s.creator = :creator')
               ->setParameter('creator', $creator);
        }

        return $qb->getQuery()->getSingleResult();
    }

    /**
     * Find surveys that need to be automatically deactivated
     */
    public function findSurveysToDeactivate(): array
    {
        $now = new \DateTimeImmutable();
        
        return $this->createQueryBuilder('s')
            ->andWhere('s.deletedAt IS NULL')
            ->andWhere('s.isActive = :active')
            ->andWhere('s.endDate IS NOT NULL')
            ->andWhere('s.endDate < :now')
            ->setParameter('active', true)
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();
    }
}