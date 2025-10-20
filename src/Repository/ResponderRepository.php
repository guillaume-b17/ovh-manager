<?php

namespace App\Repository;

use App\Entity\Responder;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Responder>
 */
class ResponderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Responder::class);
    }

    // 🔹 Exemple de méthode personnalisée (optionnelle)
    public function findActiveNow(): array
    {
        $now = new \DateTimeImmutable();
        return $this->createQueryBuilder('r')
            ->where('r.fromDate <= :now')
            ->andWhere('r.toDate >= :now')
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();
    }
}
