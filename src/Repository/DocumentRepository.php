<?php

namespace App\Repository;

use App\Entity\Client;
use App\Entity\Document;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Document>
 */
class DocumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Document::class);
    }

    /**
     * Find all non-deleted documents for a client (default behavior)
     */
    public function findByClient(Client $client): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.client = :client')
            ->andWhere('d.deletedAt IS NULL')
            ->setParameter('client', $client)
            ->orderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all deleted documents (trash)
     */
    public function findDeleted(): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.deletedAt IS NOT NULL')
            ->orderBy('d.deletedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find documents with search and filters (excluding deleted)
     * Returns paginated results with total count
     */
    public function findWithFilters(
        ?string $search = null,
        ?Client $client = null,
        ?int $categoryId = null,
        ?string $fileType = null,
        ?\DateTimeInterface $dateFrom = null,
        ?\DateTimeInterface $dateTo = null,
        int $page = 1,
        int $limit = 20
    ): array {
        $queryBuilder = $this->createQueryBuilder('d')
            ->andWhere('d.deletedAt IS NULL');

        if ($search) {
            $queryBuilder->andWhere('d.title LIKE :search OR d.originalName LIKE :search OR d.description LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        if ($client) {
            $queryBuilder->andWhere('d.client = :client')
                ->setParameter('client', $client);
        }

        if ($categoryId) {
            $queryBuilder->andWhere('d.category = :category')
                ->setParameter('category', $categoryId);
        }

        if ($fileType) {
            if ($fileType === 'pdf') {
                $queryBuilder->andWhere('d.mimeType = :mimeType')
                    ->setParameter('mimeType', 'application/pdf');
            } elseif ($fileType === 'image') {
                $queryBuilder->andWhere('d.mimeType LIKE :mimeType')
                    ->setParameter('mimeType', 'image/%');
            }
        }

        if ($dateFrom) {
            $queryBuilder->andWhere('d.createdAt >= :dateFrom')
                ->setParameter('dateFrom', $dateFrom);
        }

        if ($dateTo) {
            $queryBuilder->andWhere('d.createdAt <= :dateTo')
                ->setParameter('dateTo', $dateTo);
        }

        // Get total count
        $countQuery = clone $queryBuilder;
        $total = count($countQuery->select('d.id')->getQuery()->getResult());

        // Apply pagination
        $queryBuilder->orderBy('d.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $items = $queryBuilder->getQuery()->getResult();

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => ceil($total / $limit),
        ];
    }

    /**
     * Find recent uploads (last N documents)
     */
    public function findRecent(int $limit = 5): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.deletedAt IS NULL')
            ->orderBy('d.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find recently deleted documents
     */
    public function findRecentlyDeleted(int $limit = 5): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.deletedAt IS NOT NULL')
            ->orderBy('d.deletedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Count all non-deleted documents
     */
    public function countAll(): int
    {
        $result = $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->andWhere('d.deletedAt IS NULL')
            ->getQuery()
            ->getSingleScalarResult();

        return $result ?? 0;
    }

    /**
     * Count deleted documents
     */
    public function countDeleted(): int
    {
        $result = $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->andWhere('d.deletedAt IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();

        return $result ?? 0;
    }

    /**
     * Get total storage size
     */
    public function getTotalSize(): int
    {
        $result = $this->createQueryBuilder('d')
            ->select('SUM(d.size)')
            ->andWhere('d.deletedAt IS NULL')
            ->getQuery()
            ->getSingleScalarResult();

        return $result ?? 0;
    }

    /**
     * Find all documents (no filters)
     */
    public function findAllDocuments(?int $page = 1, ?int $limit = 20): array
    {
        return $this->findWithFilters(page: $page ?? 1, limit: $limit ?? 20);
    }
}
