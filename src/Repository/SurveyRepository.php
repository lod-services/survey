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

    public function findActiveSurveys(): array
    {
        $now = new \DateTimeImmutable();

        return $this->createQueryBuilder('s')
            ->where('s.isActive = :active')
            ->andWhere('(s.startsAt IS NULL OR s.startsAt <= :now)')
            ->andWhere('(s.endsAt IS NULL OR s.endsAt > :now)')
            ->setParameter('active', true)
            ->setParameter('now', $now)
            ->orderBy('s.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByGamificationEnabled(bool $enabled = true): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.gamificationEnabled = :enabled')
            ->setParameter('enabled', $enabled)
            ->orderBy('s.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}