<?php

namespace App\Repository;

use App\Entity\EmailAccount;
use App\Entity\Redirection;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class RedirectionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Redirection::class);
    }

    /**
     * Redirections affichées sur le portail : liées au compte ou importées OVH avec le même e-mail source.
     *
     * @return list<Redirection>
     */
    public function findForAccountPortal(EmailAccount $account): array
    {
        $email = $account->getEmail();
        if (null === $email || '' === trim($email)) {
            return $this->findBy(['fromAccount' => $account], ['toEmail' => 'ASC']);
        }

        $emailNorm = strtolower(trim($email));

        return $this->createQueryBuilder('r')
            ->where('r.fromAccount = :account OR LOWER(r.fromEmail) = :emailNorm')
            ->setParameter('account', $account)
            ->setParameter('emailNorm', $emailNorm)
            ->orderBy('r.toEmail', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
