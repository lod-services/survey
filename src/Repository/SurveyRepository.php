<?php

namespace App\Repository;

use App\Entity\Survey;
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

    public function findByCreator(int $creatorId): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.creator = :creatorId')
            ->setParameter('creatorId', $creatorId)
            ->orderBy('s.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findPublishedSurveys(): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.status = :status')
            ->setParameter('status', 'published')
            ->orderBy('s.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findWithResponsesCount(): array
    {
        return $this->createQueryBuilder('s')
            ->select('s', 'COUNT(r.id) as responseCount')
            ->leftJoin('s.responses', 'r')
            ->groupBy('s.id')
            ->orderBy('s.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}