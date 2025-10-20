<?php

namespace App\Entity;

use App\Repository\ResponderRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ResponderRepository::class)]
class Responder
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: EmailAccount::class, inversedBy: 'responders')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?EmailAccount $emailAccount = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $content = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $fromDate = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $toDate = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $copy = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $copyTo = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $dateSync = null;

    public function getId(): ?int { return $this->id; }

    public function getEmailAccount(): ?EmailAccount { return $this->emailAccount; }
    public function setEmailAccount(?EmailAccount $emailAccount): self { $this->emailAccount = $emailAccount; return $this; }

    public function getContent(): ?string { return $this->content; }
    public function setContent(?string $content): self { $this->content = $content; return $this; }

    public function getFromDate(): ?\DateTimeImmutable { return $this->fromDate; }
    public function setFromDate(?\DateTimeImmutable $fromDate): self { $this->fromDate = $fromDate; return $this; }

    public function getToDate(): ?\DateTimeImmutable { return $this->toDate; }
    public function setToDate(?\DateTimeImmutable $toDate): self { $this->toDate = $toDate; return $this; }

    public function getCopy(): ?bool { return $this->copy; }
    public function setCopy(?bool $copy): self { $this->copy = $copy; return $this; }

    public function getCopyTo(): ?string { return $this->copyTo; }
    public function setCopyTo(?string $copyTo): self { $this->copyTo = $copyTo; return $this; }

    public function getDateSync(): ?\DateTimeImmutable { return $this->dateSync; }
    public function setDateSync(?\DateTimeImmutable $dateSync): self { $this->dateSync = $dateSync; return $this; }

    public function __toString(): string
    {
        return $this->emailAccount ? 'Répondeur de '.$this->emailAccount->getEmail() : 'Répondeur';
    }

    public function getStatusLabel(): string
    {
        $now = new \DateTimeImmutable();

        if ($this->getFromDate() && $this->getToDate()) {
            if ($now >= $this->getFromDate() && $now <= $this->getToDate()) {
                return '🟢 Actif';
            }
            if ($now > $this->getToDate()) {
                return '🔴 Expiré';
            }
            if ($now < $this->getFromDate()) {
                return '⚪ Inactif';
            }
        }

        return '⚪ Inactif';
    }

    public function isCopy(): ?bool
    {
        return $this->getCopy();
    }


}
