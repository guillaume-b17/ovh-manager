<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\EmailLoginChallenge;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EmailLoginChallenge>
 */
class EmailLoginChallengeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EmailLoginChallenge::class);
    }

    public function findLatestActiveForEmail(string $email, \DateTimeImmutable $now): ?EmailLoginChallenge
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.email = :email')
            ->andWhere('c.consumedAt IS NULL')
            ->andWhere('c.expiresAt > :now')
            ->setParameter('email', $email)
            ->setParameter('now', $now)
            ->orderBy('c.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function countRecentForEmail(string $email, \DateTimeImmutable $since): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.email = :email')
            ->andWhere('c.createdAt > :since')
            ->setParameter('email', $email)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return EmailLoginChallenge[]
     */
    public function findPendingForEmail(string $email): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.email = :email')
            ->andWhere('c.consumedAt IS NULL')
            ->setParameter('email', $email)
            ->getQuery()
            ->getResult();
    }
}
