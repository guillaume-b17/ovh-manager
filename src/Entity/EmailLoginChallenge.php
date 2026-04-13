<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\EmailLoginChallengeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EmailLoginChallengeRepository::class)]
#[ORM\Table(name: 'email_login_challenge')]
#[ORM\Index(name: 'idx_email_login_challenge_email', columns: ['email'])]
#[ORM\Index(name: 'idx_email_login_challenge_expires', columns: ['expires_at'])]
class EmailLoginChallenge
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private string $email = '';

    #[ORM\Column(length: 255)]
    private string $codeHash = '';

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $consumedAt = null;

    #[ORM\Column(type: 'smallint', options: ['default' => 0])]
    private int $attempts = 0;

    public function __construct(string $email, string $codeHash, \DateTimeImmutable $createdAt, \DateTimeImmutable $expiresAt)
    {
        $this->email = $email;
        $this->codeHash = $codeHash;
        $this->createdAt = $createdAt;
        $this->expiresAt = $expiresAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getCodeHash(): string
    {
        return $this->codeHash;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getConsumedAt(): ?\DateTimeImmutable
    {
        return $this->consumedAt;
    }

    public function setConsumedAt(?\DateTimeImmutable $consumedAt): self
    {
        $this->consumedAt = $consumedAt;

        return $this;
    }

    public function getAttempts(): int
    {
        return $this->attempts;
    }

    public function incrementAttempts(): self
    {
        ++$this->attempts;

        return $this;
    }

    public function isConsumed(): bool
    {
        return null !== $this->consumedAt;
    }

    public function isExpired(\DateTimeImmutable $now): bool
    {
        return $now >= $this->expiresAt;
    }
}
