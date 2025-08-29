<?php

namespace App\Repository;

use App\Entity\UserAchievement;
use App\Entity\User;
use App\Entity\AchievementDefinition;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserAchievement>
 */
class UserAchievementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserAchievement::class);
    }

    public function hasUserUnlockedAchievement(User $user, AchievementDefinition $achievement): bool
    {
        return $this->createQueryBuilder('ua')
            ->select('COUNT(ua.id)')
            ->where('ua.user = :user')
            ->andWhere('ua.achievement = :achievement')
            ->setParameter('user', $user)
            ->setParameter('achievement', $achievement)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    public function findUserAchievements(User $user): array
    {
        return $this->createQueryBuilder('ua')
            ->leftJoin('ua.achievement', 'a')
            ->where('ua.user = :user')
            ->setParameter('user', $user)
            ->orderBy('ua.unlockedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findRecentAchievements(User $user, int $limit = 5): array
    {
        return $this->createQueryBuilder('ua')
            ->leftJoin('ua.achievement', 'a')
            ->where('ua.user = :user')
            ->setParameter('user', $user)
            ->orderBy('ua.unlockedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}