<?php

namespace App\Repository;

use App\Entity\AchievementDefinition;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AchievementDefinition>
 */
class AchievementDefinitionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AchievementDefinition::class);
    }

    public function findActiveAchievements(): array
    {
        return $this->createQueryBuilder('ad')
            ->where('ad.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('ad.points', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByType(string $type): array
    {
        return $this->createQueryBuilder('ad')
            ->where('ad.type = :type')
            ->andWhere('ad.isActive = :active')
            ->setParameter('type', $type)
            ->setParameter('active', true)
            ->orderBy('ad.points', 'ASC')
            ->getQuery()
            ->getResult();
    }
}