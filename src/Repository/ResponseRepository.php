<?php

namespace App\Repository;

use App\Entity\Response;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Response>
 */
class ResponseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Response::class);
    }

    public function findBySession(int $sessionId): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.session = :sessionId')
            ->setParameter('sessionId', $sessionId)
            ->orderBy('r.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findBySessionAndQuestion(int $sessionId, int $questionId): ?Response
    {
        return $this->findOneBy([
            'session' => $sessionId,
            'question' => $questionId
        ]);
    }
}