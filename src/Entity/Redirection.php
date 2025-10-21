<?php

namespace App\Entity;

use App\Repository\RedirectionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RedirectionRepository::class)]
class Redirection
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $domain;

    #[ORM\Column(length: 255)]
    private string $fromEmail;

    #[ORM\Column(length: 255)]
    private string $toEmail;

    #[ORM\Column(options: ['default' => false])]
    private bool $localCopy = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $ovhId = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $syncedAt = null;

    #[ORM\ManyToOne(targetEntity: EmailAccount::class)]
    private ?EmailAccount $fromAccount = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $ovhTaskId = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function setDomain(string $domain): self
    {
        $this->domain = $domain;
        return $this;
    }

    public function getFromEmail(): string
    {
        return $this->fromEmail;
    }

    public function setFromEmail(string $fromEmail): self
    {
        $this->fromEmail = $fromEmail;
        return $this;
    }

    public function getToEmail(): string
    {
        return $this->toEmail;
    }

    public function setToEmail(string $toEmail): self
    {
        $this->toEmail = $toEmail;
        return $this;
    }

    public function isLocalCopy(): bool
    {
        return $this->localCopy;
    }

    public function setLocalCopy(bool $localCopy): self
    {
        $this->localCopy = $localCopy;
        return $this;
    }

    public function getOvhId(): ?string
    {
        return $this->ovhId;
    }

    public function setOvhId(?string $ovhId): self
    {
        $this->ovhId = $ovhId;
        return $this;
    }

    public function getSyncedAt(): ?\DateTimeInterface
    {
        return $this->syncedAt;
    }

    public function setSyncedAt(?\DateTimeInterface $syncedAt): self
    {
        $this->syncedAt = $syncedAt;
        return $this;
    }

    public function getFromAccount(): ?EmailAccount
    {
        return $this->fromAccount;
    }

    public function setFromAccount(?EmailAccount $account): self
    {
        $this->fromAccount = $account;
        return $this;
    }


    public function getOvhTaskId(): ?string { return $this->ovhTaskId; }
    public function setOvhTaskId(?string $id): self { $this->ovhTaskId = $id; return $this; }

    public function __toString(): string
    {
        return sprintf('%s → %s', $this->fromEmail, $this->toEmail);
    }
}
