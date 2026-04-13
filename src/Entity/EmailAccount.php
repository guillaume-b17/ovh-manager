<?php

namespace App\Entity;

use App\Repository\EmailAccountRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EmailAccountRepository::class)]
class EmailAccount
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $domain = null;

    #[ORM\Column(length: 255)]
    private ?string $accountName = null;

    #[ORM\Column(type: 'bigint', nullable: true)]
    private ?string $size = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $dateSync = null;

    #[ORM\Column(length: 255)]
    private ?string $email = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $displayName = null;

    #[ORM\Column(type: 'bigint', nullable: true)]
    private ?string $usageQuota = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $usageEmailCount = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $usageDate = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    #[ORM\OneToMany(mappedBy: 'emailAccount', targetEntity: Responder::class, cascade: ['persist', 'remove'])]
    private Collection $responders;

    public function __construct()
    {
        $this->responders = new ArrayCollection();
    }

    public function getResponders(): Collection
    {
        return $this->responders;
    }


    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDomain(): ?string
    {
        return $this->domain;
    }

    public function setDomain(string $domain): static
    {
        $this->domain = $domain;

        return $this;
    }

    public function getAccountName(): ?string
    {
        return $this->accountName;
    }

    public function setAccountName(string $accountName): static
    {
        $this->accountName = $accountName;

        return $this;
    }

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function setSize(?int $size): static
    {
        $this->size = $size;

        return $this;
    }

    public function getDateSync(): ?\DateTimeImmutable
    {
        return $this->dateSync;
    }

    public function setDateSync(?\DateTimeImmutable $dateSync): static
    {
        $this->dateSync = $dateSync;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getDisplayName(): ?string
    {
        return $this->displayName;
    }

    public function setDisplayName(?string $displayName): static
    {
        $this->displayName = $displayName;

        return $this;
    }

    public function getSizeInGo(): ?float
    {
        return $this->size ? round(((int)$this->size) / 1024 / 1024 / 1024, 2) : null;
    }

    public function getQuotaInGo(): ?float
    {
        return $this->quota ? round(((int)$this->quota) / 1024 / 1024 / 1024, 2) : null;
    }

    public function getUsagePercent(): ?float
    {
        // usageQuota = quota utilisé
        // size = taille totale (max) renvoyée par OVH

        $used = $this->getUsageQuota();
        $max  = $this->getSize();

        if (!$used || !$max || (int)$max === 0) {
            return null;
        }

        return round(((int)$used / (int)$max) * 100, 1);
    }


    public function getUsageQuota(): ?string
    {
        return $this->usageQuota;
    }

    public function setUsageQuota(?string $usageQuota): self
    {
        $this->usageQuota = $usageQuota;
        return $this;
    }

    public function getUsageEmailCount(): ?int
    {
        return $this->usageEmailCount;
    }

    public function setUsageEmailCount(?int $usageEmailCount): self
    {
        $this->usageEmailCount = $usageEmailCount;
        return $this;
    }

    public function getUsageDate(): ?\DateTimeImmutable
    {
        return $this->usageDate;
    }

    public function setUsageDate(?\DateTimeImmutable $usageDate): self
    {
        $this->usageDate = $usageDate;
        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function __toString(): string
    {
        // TODO: Implement __toString() method.
        return $this->email;
    }

}
