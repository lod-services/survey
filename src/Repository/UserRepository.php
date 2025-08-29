<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    public function findByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => $email]);
    }

    public function getTopPointsLeaderboard(int $limit = 10): array
    {
        return $this->createQueryBuilder('u')
            ->orderBy('u.totalPoints', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function getUserRankByPoints(User $user): int
    {
        $rank = $this->createQueryBuilder('u')
            ->select('COUNT(u2.id) + 1')
            ->leftJoin(User::class, 'u2', 'WITH', 'u2.totalPoints > u.totalPoints')
            ->where('u.id = :userId')
            ->setParameter('userId', $user->getId())
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $rank;
    }

    public function findActiveUsers(\DateTimeImmutable $since = null): array
    {
        $qb = $this->createQueryBuilder('u')
            ->where('u.lastActiveAt IS NOT NULL');

        if ($since) {
            $qb->andWhere('u.lastActiveAt >= :since')
               ->setParameter('since', $since);
        }

        return $qb->orderBy('u.lastActiveAt', 'DESC')
                  ->getQuery()
                  ->getResult();
    }
}