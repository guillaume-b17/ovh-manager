<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\OrganizationSettingRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Paramètres globaux (une ligne prévue, id 1) pour les modèles de répondeur.
 */
#[ORM\Entity(repositoryClass: OrganizationSettingRepository::class)]
#[ORM\Table(name: 'organization_setting')]
class OrganizationSetting
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $agencyPhone = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAgencyPhone(): ?string
    {
        return $this->agencyPhone;
    }

    public function setAgencyPhone(?string $agencyPhone): self
    {
        $this->agencyPhone = $agencyPhone;

        return $this;
    }
}
