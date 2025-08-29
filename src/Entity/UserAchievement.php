<?php

namespace App\Entity;

use App\Repository\UserAchievementRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserAchievementRepository::class)]
#[ORM\UniqueConstraint(name: 'user_achievement_unique', columns: ['user_id', 'achievement_id'])]
class UserAchievement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'achievements')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'userAchievements')]
    #[ORM\JoinColumn(nullable: false)]
    private ?AchievementDefinition $achievement = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $unlockedAt = null;

    #[ORM\Column]
    private int $pointsAwarded = 0;

    #[ORM\Column]
    private bool $isNotified = false;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $context = null;

    public function __construct()
    {
        $this->unlockedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getAchievement(): ?AchievementDefinition
    {
        return $this->achievement;
    }

    public function setAchievement(?AchievementDefinition $achievement): static
    {
        $this->achievement = $achievement;

        return $this;
    }

    public function getUnlockedAt(): ?\DateTimeImmutable
    {
        return $this->unlockedAt;
    }

    public function setUnlockedAt(\DateTimeImmutable $unlockedAt): static
    {
        $this->unlockedAt = $unlockedAt;

        return $this;
    }

    public function getPointsAwarded(): int
    {
        return $this->pointsAwarded;
    }

    public function setPointsAwarded(int $pointsAwarded): static
    {
        $this->pointsAwarded = $pointsAwarded;

        return $this;
    }

    public function isNotified(): bool
    {
        return $this->isNotified;
    }

    public function setIsNotified(bool $isNotified): static
    {
        $this->isNotified = $isNotified;

        return $this;
    }

    public function getContext(): ?string
    {
        return $this->context;
    }

    public function setContext(?string $context): static
    {
        $this->context = $context;

        return $this;
    }
}