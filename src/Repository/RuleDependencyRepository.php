<?php

namespace App\Repository;

use App\Entity\RuleDependency;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RuleDependency>
 */
class RuleDependencyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RuleDependency::class);
    }

    public function findCircularDependencies(int $ruleId): array
    {
        return $this->createQueryBuilder('rd')
            ->where('rd.parentRule = :ruleId')
            ->andWhere('rd.childRule IN (
                SELECT rd2.parentRule FROM App\Entity\RuleDependency rd2 
                WHERE rd2.childRule = :ruleId
            )')
            ->setParameter('ruleId', $ruleId)
            ->getQuery()
            ->getResult();
    }
}