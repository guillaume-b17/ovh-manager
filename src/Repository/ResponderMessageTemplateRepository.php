<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ResponderMessageTemplate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ResponderMessageTemplate>
 */
class ResponderMessageTemplateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ResponderMessageTemplate::class);
    }

    /**
     * @return list<ResponderMessageTemplate>
     */
    public function findActiveOrdered(): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.enabled = :enabled')
            ->setParameter('enabled', true)
            ->orderBy('t.sortOrder', 'ASC')
            ->addOrderBy('t.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findActiveByCode(string $code): ?ResponderMessageTemplate
    {
        return $this->findOneBy(['code' => $code, 'enabled' => true]);
    }
}
