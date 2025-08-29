<?php

namespace App\Repository;

use App\Entity\UserStreak;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserStreak>
 */
class UserStreakRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserStreak::class);
    }

    public function findOrCreateForUser(User $user): UserStreak
    {
        $streak = $this->findOneBy(['user' => $user]);
        
        if (!$streak) {
            $streak = new UserStreak();
            $streak->setUser($user);
            $streak->setTimezone($user->getTimezone() ?? 'UTC');
        }
        
        return $streak;
    }

    public function findTopStreaks(int $limit = 10): array
    {
        return $this->createQueryBuilder('us')
            ->leftJoin('us.user', 'u')
            ->where('us.currentStreak > 0')
            ->orderBy('us.currentStreak', 'DESC')
            ->addOrderBy('us.lastActivityAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findStreaksToUpdate(): array
    {
        // Find streaks that may need to be broken due to inactivity
        $oneDayAgo = new \DateTimeImmutable('-2 days');
        
        return $this->createQueryBuilder('us')
            ->where('us.currentStreak > 0')
            ->andWhere('us.lastActivityAt < :oneDayAgo')
            ->setParameter('oneDayAgo', $oneDayAgo)
            ->getQuery()
            ->getResult();
    }
}