<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\OrganizationSetting;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OrganizationSetting>
 */
class OrganizationSettingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrganizationSetting::class);
    }

    public function getSingleton(): OrganizationSetting
    {
        $row = $this->find(1);
        if (null !== $row) {
            return $row;
        }

        $fallback = $this->findOneBy([]);
        if (null !== $fallback) {
            return $fallback;
        }

        $created = new OrganizationSetting();
        $this->getEntityManager()->persist($created);
        $this->getEntityManager()->flush();

        return $created;
    }
}
