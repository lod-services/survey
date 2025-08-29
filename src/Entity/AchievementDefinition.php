<?php

namespace App\Entity;

use App\Repository\AchievementDefinitionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AchievementDefinitionRepository::class)]
class AchievementDefinition
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $description = null;

    #[ORM\Column(length: 255)]
    private ?string $icon = null;

    #[ORM\Column]
    private int $points = 0;

    #[ORM\Column(length: 50)]
    private ?string $type = null;

    #[ORM\Column(type: Types::JSON)]
    private array $criteria = [];

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    /**
     * @var Collection<int, UserAchievement>
     */
    #[ORM\OneToMany(targetEntity: UserAchievement::class, mappedBy: 'achievement', orphanRemoval: true)]
    private Collection $userAchievements;

    public function __construct()
    {
        $this->userAchievements = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function setIcon(string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    public function getPoints(): int
    {
        return $this->points;
    }

    public function setPoints(int $points): static
    {
        $this->points = $points;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getCriteria(): array
    {
        return $this->criteria;
    }

    public function setCriteria(array $criteria): static
    {
        $this->criteria = $criteria;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

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

    /**
     * @return Collection<int, UserAchievement>
     */
    public function getUserAchievements(): Collection
    {
        return $this->userAchievements;
    }

    public function addUserAchievement(UserAchievement $userAchievement): static
    {
        if (!$this->userAchievements->contains($userAchievement)) {
            $this->userAchievements->add($userAchievement);
            $userAchievement->setAchievement($this);
        }

        return $this;
    }

    public function removeUserAchievement(UserAchievement $userAchievement): static
    {
        if ($this->userAchievements->removeElement($userAchievement)) {
            if ($userAchievement->getAchievement() === $this) {
                $userAchievement->setAchievement(null);
            }
        }

        return $this;
    }

    public function checkCriteria(User $user, array $context = []): bool
    {
        switch ($this->type) {
            case 'first_survey':
                return $user->getSurveyResponses()->filter(fn($response) => $response->isCompleted())->count() >= 1;
                
            case 'survey_count':
                return $user->getSurveyResponses()->filter(fn($response) => $response->isCompleted())->count() >= ($this->criteria['count'] ?? 1);
                
            case 'points_threshold':
                return $user->getTotalPoints() >= ($this->criteria['points'] ?? 100);
                
            case 'streak_milestone':
                return $user->getStreak()?->getCurrentStreak() >= ($this->criteria['days'] ?? 5);
                
            case 'quality_score':
                $qualityResponses = $user->getSurveyResponses()
                    ->filter(fn($response) => $response->isCompleted() && $response->getQualityScore() >= ($this->criteria['min_score'] ?? 0.8));
                return $qualityResponses->count() >= ($this->criteria['count'] ?? 1);
                
            default:
                return false;
        }
    }
}