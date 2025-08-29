<?php

namespace App\Entity;

use App\Repository\SurveyRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SurveyRepository::class)]
class Survey
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $startsAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $endsAt = null;

    #[ORM\Column]
    private int $basePoints = 10;

    #[ORM\Column]
    private int $qualityMultiplier = 2;

    #[ORM\Column(nullable: true)]
    private ?int $minimumTimeSeconds = null;

    #[ORM\Column]
    private bool $gamificationEnabled = true;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $formFields = null;

    /**
     * @var Collection<int, SurveyResponse>
     */
    #[ORM\OneToMany(targetEntity: SurveyResponse::class, mappedBy: 'survey', orphanRemoval: true)]
    private Collection $responses;

    /**
     * @var Collection<int, UserPoint>
     */
    #[ORM\OneToMany(targetEntity: UserPoint::class, mappedBy: 'survey', orphanRemoval: true)]
    private Collection $pointsAwarded;

    public function __construct()
    {
        $this->responses = new ArrayCollection();
        $this->pointsAwarded = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

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

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

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

    public function getStartsAt(): ?\DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function setStartsAt(?\DateTimeImmutable $startsAt): static
    {
        $this->startsAt = $startsAt;

        return $this;
    }

    public function getEndsAt(): ?\DateTimeImmutable
    {
        return $this->endsAt;
    }

    public function setEndsAt(?\DateTimeImmutable $endsAt): static
    {
        $this->endsAt = $endsAt;

        return $this;
    }

    public function getBasePoints(): int
    {
        return $this->basePoints;
    }

    public function setBasePoints(int $basePoints): static
    {
        $this->basePoints = $basePoints;

        return $this;
    }

    public function getQualityMultiplier(): int
    {
        return $this->qualityMultiplier;
    }

    public function setQualityMultiplier(int $qualityMultiplier): static
    {
        $this->qualityMultiplier = $qualityMultiplier;

        return $this;
    }

    public function getMinimumTimeSeconds(): ?int
    {
        return $this->minimumTimeSeconds;
    }

    public function setMinimumTimeSeconds(?int $minimumTimeSeconds): static
    {
        $this->minimumTimeSeconds = $minimumTimeSeconds;

        return $this;
    }

    public function isGamificationEnabled(): bool
    {
        return $this->gamificationEnabled;
    }

    public function setGamificationEnabled(bool $gamificationEnabled): static
    {
        $this->gamificationEnabled = $gamificationEnabled;

        return $this;
    }

    public function getFormFields(): ?array
    {
        return $this->formFields;
    }

    public function setFormFields(?array $formFields): static
    {
        $this->formFields = $formFields;

        return $this;
    }

    /**
     * @return Collection<int, SurveyResponse>
     */
    public function getResponses(): Collection
    {
        return $this->responses;
    }

    public function addResponse(SurveyResponse $response): static
    {
        if (!$this->responses->contains($response)) {
            $this->responses->add($response);
            $response->setSurvey($this);
        }

        return $this;
    }

    public function removeResponse(SurveyResponse $response): static
    {
        if ($this->responses->removeElement($response)) {
            if ($response->getSurvey() === $this) {
                $response->setSurvey(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, UserPoint>
     */
    public function getPointsAwarded(): Collection
    {
        return $this->pointsAwarded;
    }

    public function addPointsAwarded(UserPoint $pointsAwarded): static
    {
        if (!$this->pointsAwarded->contains($pointsAwarded)) {
            $this->pointsAwarded->add($pointsAwarded);
            $pointsAwarded->setSurvey($this);
        }

        return $this;
    }

    public function removePointsAwarded(UserPoint $pointsAwarded): static
    {
        if ($this->pointsAwarded->removeElement($pointsAwarded)) {
            if ($pointsAwarded->getSurvey() === $this) {
                $pointsAwarded->setSurvey(null);
            }
        }

        return $this;
    }

    public function isAvailable(): bool
    {
        if (!$this->isActive) {
            return false;
        }

        $now = new \DateTimeImmutable();

        if ($this->startsAt && $now < $this->startsAt) {
            return false;
        }

        if ($this->endsAt && $now > $this->endsAt) {
            return false;
        }

        return true;
    }
}