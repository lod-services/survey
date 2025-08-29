<?php

namespace App\Repository;

use App\Entity\UserPoint;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserPoint>
 */
class UserPointRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserPoint::class);
    }

    public function findUserPointsHistory(User $user, int $limit = 50): array
    {
        return $this->createQueryBuilder('up')
            ->where('up.user = :user')
            ->setParameter('user', $user)
            ->orderBy('up.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function getUserPointsInPeriod(User $user, \DateTimeImmutable $start, \DateTimeImmutable $end): int
    {
        $result = $this->createQueryBuilder('up')
            ->select('SUM(up.points)')
            ->where('up.user = :user')
            ->andWhere('up.createdAt >= :start')
            ->andWhere('up.createdAt <= :end')
            ->setParameter('user', $user)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) ($result ?? 0);
    }
}