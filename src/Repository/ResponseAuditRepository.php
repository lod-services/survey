<?php

namespace App\Repository;

use App\Entity\ResponseAudit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ResponseAudit>
 */
class ResponseAuditRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ResponseAudit::class);
    }

    public function findByResponse(int $responseId): array
    {
        return $this->createQueryBuilder('ra')
            ->where('ra.response = :responseId')
            ->setParameter('responseId', $responseId)
            ->orderBy('ra.timestamp', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByRule(int $ruleId, \DateTime $since = null): array
    {
        $qb = $this->createQueryBuilder('ra')
            ->where('ra.rule = :ruleId')
            ->setParameter('ruleId', $ruleId);

        if ($since) {
            $qb->andWhere('ra.timestamp >= :since')
               ->setParameter('since', $since);
        }

        return $qb->orderBy('ra.timestamp', 'DESC')
                  ->getQuery()
                  ->getResult();
    }
}