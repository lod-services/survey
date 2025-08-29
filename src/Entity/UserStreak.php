<?php

namespace App\Entity;

use App\Repository\UserStreakRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserStreakRepository::class)]
class UserStreak
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'streak', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column]
    private int $currentStreak = 0;

    #[ORM\Column]
    private int $longestStreak = 0;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastActivityAt = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $timezone = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $streakStartedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getCurrentStreak(): int
    {
        return $this->currentStreak;
    }

    public function setCurrentStreak(int $currentStreak): static
    {
        $this->currentStreak = $currentStreak;

        return $this;
    }

    public function getLongestStreak(): int
    {
        return $this->longestStreak;
    }

    public function setLongestStreak(int $longestStreak): static
    {
        $this->longestStreak = $longestStreak;

        return $this;
    }

    public function getLastActivityAt(): ?\DateTimeImmutable
    {
        return $this->lastActivityAt;
    }

    public function setLastActivityAt(?\DateTimeImmutable $lastActivityAt): static
    {
        $this->lastActivityAt = $lastActivityAt;

        return $this;
    }

    public function getTimezone(): ?string
    {
        return $this->timezone;
    }

    public function setTimezone(?string $timezone): static
    {
        $this->timezone = $timezone;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getStreakStartedAt(): ?\DateTimeImmutable
    {
        return $this->streakStartedAt;
    }

    public function setStreakStartedAt(?\DateTimeImmutable $streakStartedAt): static
    {
        $this->streakStartedAt = $streakStartedAt;

        return $this;
    }

    public function incrementStreak(): static
    {
        $this->currentStreak++;
        
        if ($this->currentStreak > $this->longestStreak) {
            $this->longestStreak = $this->currentStreak;
        }

        if ($this->currentStreak === 1) {
            $this->streakStartedAt = new \DateTimeImmutable();
        }

        $this->lastActivityAt = new \DateTimeImmutable();

        return $this;
    }

    public function resetStreak(): static
    {
        $this->currentStreak = 0;
        $this->streakStartedAt = null;

        return $this;
    }

    public function isStreakActive(?\DateTimeZone $userTimezone = null): bool
    {
        if (!$this->lastActivityAt) {
            return false;
        }

        $timezone = $userTimezone ?: new \DateTimeZone($this->timezone ?: 'UTC');
        $now = new \DateTime('now', $timezone);
        $lastActivity = $this->lastActivityAt->setTimezone($timezone);

        // Calculate days difference based on calendar days
        $nowDate = $now->format('Y-m-d');
        $lastActivityDate = $lastActivity->format('Y-m-d');

        $daysDifference = (int) $now->diff($lastActivity)->format('%a');

        // Streak is active if last activity was today or yesterday
        return $daysDifference <= 1;
    }

    public function shouldBreakStreak(?\DateTimeZone $userTimezone = null): bool
    {
        if (!$this->lastActivityAt) {
            return false;
        }

        $timezone = $userTimezone ?: new \DateTimeZone($this->timezone ?: 'UTC');
        $now = new \DateTime('now', $timezone);
        $lastActivity = $this->lastActivityAt->setTimezone($timezone);

        $daysDifference = (int) $now->diff($lastActivity)->format('%a');

        // Break streak if no activity for more than 1 day
        return $daysDifference > 1;
    }
}