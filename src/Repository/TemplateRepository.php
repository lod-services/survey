<?php

namespace App\Repository;

use App\Entity\Template;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Template>
 *
 * @method Template|null find($id, $lockMode = null, $lockVersion = null)
 * @method Template|null findOneBy(array $criteria, array $orderBy = null)
 * @method Template[]    findAll()
 * @method Template[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TemplateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Template::class);
    }

    /**
     * Find templates by industry
     */
    public function findByIndustry(string $industry): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.industry = :industry')
            ->andWhere('t.status = :status')
            ->setParameter('industry', $industry)
            ->setParameter('status', 'active')
            ->orderBy('t.usageCount', 'DESC')
            ->addOrderBy('t.averageRating', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find templates by multiple criteria with filtering
     */
    public function findWithFilters(array $filters = []): array
    {
        $qb = $this->createQueryBuilder('t')
            ->andWhere('t.status = :status')
            ->setParameter('status', 'active');

        if (!empty($filters['industry'])) {
            $qb->andWhere('t.industry = :industry')
               ->setParameter('industry', $filters['industry']);
        }

        if (!empty($filters['category'])) {
            $qb->andWhere('t.category = :category')
               ->setParameter('category', $filters['category']);
        }

        if (!empty($filters['tags'])) {
            $qb->andWhere('JSON_CONTAINS(t.tags, :tags) = 1')
               ->setParameter('tags', json_encode($filters['tags']));
        }

        if (!empty($filters['search'])) {
            $qb->andWhere($qb->expr()->orX(
                $qb->expr()->like('t.name', ':search'),
                $qb->expr()->like('t.description', ':search')
            ))
            ->setParameter('search', '%' . $filters['search'] . '%');
        }

        // Default ordering by popularity and rating
        $orderBy = $filters['orderBy'] ?? 'popularity';
        switch ($orderBy) {
            case 'rating':
                $qb->orderBy('t.averageRating', 'DESC');
                break;
            case 'newest':
                $qb->orderBy('t.createdAt', 'DESC');
                break;
            case 'name':
                $qb->orderBy('t.name', 'ASC');
                break;
            default: // popularity
                $qb->orderBy('t.usageCount', 'DESC')
                   ->addOrderBy('t.averageRating', 'DESC');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Get popular templates for recommendations
     */
    public function findPopularTemplates(int $limit = 10): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.status = :status')
            ->setParameter('status', 'active')
            ->orderBy('t.usageCount', 'DESC')
            ->addOrderBy('t.averageRating', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get templates by compliance flags
     */
    public function findByComplianceFlags(array $complianceFlags): array
    {
        $qb = $this->createQueryBuilder('t')
            ->andWhere('t.status = :status')
            ->setParameter('status', 'active');

        foreach ($complianceFlags as $index => $flag) {
            $qb->andWhere("JSON_CONTAINS(t.complianceFlags, :flag{$index}) = 1")
               ->setParameter("flag{$index}", json_encode($flag));
        }

        return $qb->orderBy('t.usageCount', 'DESC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Get all industries from active templates
     */
    public function getAllIndustries(): array
    {
        $result = $this->createQueryBuilder('t')
            ->select('DISTINCT t.industry')
            ->andWhere('t.status = :status')
            ->setParameter('status', 'active')
            ->orderBy('t.industry', 'ASC')
            ->getQuery()
            ->getResult();

        return array_column($result, 'industry');
    }

    /**
     * Get all categories from active templates
     */
    public function getAllCategories(): array
    {
        $result = $this->createQueryBuilder('t')
            ->select('DISTINCT t.category')
            ->andWhere('t.status = :status')
            ->setParameter('status', 'active')
            ->orderBy('t.category', 'ASC')
            ->getQuery()
            ->getResult();

        return array_column($result, 'category');
    }
}