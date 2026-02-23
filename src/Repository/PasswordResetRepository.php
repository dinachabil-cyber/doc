<?php

namespace App\Repository;

use App\Entity\PasswordReset;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PasswordReset>
 */
class PasswordResetRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PasswordReset::class);
    }

    /**
     * Find a valid (non-expired, non-used) token by hash
     */
    public function findValidToken(string $tokenHash): ?PasswordReset
    {
        return $this->createQueryBuilder('pr')
            ->where('pr.tokenHash = :tokenHash')
            ->andWhere('pr.expiresAt > :now')
            ->andWhere('pr.usedAt IS NULL')
            ->setParameter('tokenHash', $tokenHash)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find all valid tokens for a user
     */
    public function findValidTokensByUser(int $userId): array
    {
        return $this->createQueryBuilder('pr')
            ->where('pr.user = :userId')
            ->andWhere('pr.expiresAt > :now')
            ->andWhere('pr.usedAt IS NULL')
            ->setParameter('userId', $userId)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getResult();
    }

    /**
     * Invalidate all tokens for a user (used after password change)
     */
    public function invalidateAllForUser(int $userId): int
    {
        return $this->createQueryBuilder('pr')
            ->update()
            ->set('pr.usedAt', ':now')
            ->where('pr.user = :userId')
            ->andWhere('pr.usedAt IS NULL')
            ->setParameter('userId', $userId)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->execute();
    }

    /**
     * Clean up expired tokens (older than specified days)
     */
    public function cleanupExpired(int $daysOld = 7): int
    {
        $cutoffDate = (new \DateTimeImmutable())->modify("-{$daysOld} days");
        
        return $this->createQueryBuilder('pr')
            ->delete()
            ->where('pr.createdAt < :cutoffDate')
            ->setParameter('cutoffDate', $cutoffDate)
            ->getQuery()
            ->execute();
    }
}
