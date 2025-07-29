<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'login_attempts')]
#[ORM\Index(columns: ['ip_address', 'attempted_at'], name: 'idx_ip_attempt_time')]
#[ORM\Index(columns: ['username', 'attempted_at'], name: 'idx_username_attempt_time')]
class LoginAttempt
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 45)]
    private ?string $ipAddress = null;

    #[ORM\Column(type: 'string', length: 180, nullable: true)]
    private ?string $username = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $attemptedAt = null;

    #[ORM\Column(type: 'boolean')]
    private bool $successful = false;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $failureReason = null;

    public function __construct()
    {
        $this->attemptedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(string $ipAddress): static
    {
        $this->ipAddress = $ipAddress;
        return $this;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(?string $username): static
    {
        $this->username = $username;
        return $this;
    }

    public function getAttemptedAt(): ?\DateTimeInterface
    {
        return $this->attemptedAt;
    }

    public function setAttemptedAt(\DateTimeInterface $attemptedAt): static
    {
        $this->attemptedAt = $attemptedAt;
        return $this;
    }

    public function isSuccessful(): bool
    {
        return $this->successful;
    }

    public function setSuccessful(bool $successful): static
    {
        $this->successful = $successful;
        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): static
    {
        $this->userAgent = $userAgent;
        return $this;
    }

    public function getFailureReason(): ?string
    {
        return $this->failureReason;
    }

    public function setFailureReason(?string $failureReason): static
    {
        $this->failureReason = $failureReason;
        return $this;
    }
}