<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Template
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $description = null;

    #[ORM\Column(length: 100)]
    private ?string $industry = null;

    #[ORM\Column(length: 100)]
    private ?string $category = null;

    #[ORM\Column(type: Types::JSON)]
    private array $questionSchema = [];

    #[ORM\Column(type: Types::JSON)]
    private array $complianceRequirements = [];

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $customizationOptions = null;

    #[ORM\Column]
    private ?bool $isPublic = true;

    #[ORM\Column]
    private ?bool $isVerified = false;

    #[ORM\Column]
    private ?int $usageCount = 0;

    #[ORM\Column(type: Types::DECIMAL, precision: 3, scale: 2, nullable: true)]
    private ?string $averageRating = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(inversedBy: 'templates')]
    private ?User $createdBy = null;

    #[ORM\OneToMany(targetEntity: TemplateRating::class, mappedBy: 'template', orphanRemoval: true)]
    private Collection $ratings;

    public function __construct()
    {
        $this->ratings = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
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
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getIndustry(): ?string
    {
        return $this->industry;
    }

    public function setIndustry(string $industry): static
    {
        $this->industry = $industry;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(string $category): static
    {
        $this->category = $category;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getQuestionSchema(): array
    {
        return $this->questionSchema;
    }

    public function setQuestionSchema(array $questionSchema): static
    {
        $this->questionSchema = $questionSchema;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getComplianceRequirements(): array
    {
        return $this->complianceRequirements;
    }

    public function setComplianceRequirements(array $complianceRequirements): static
    {
        $this->complianceRequirements = $complianceRequirements;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getCustomizationOptions(): ?array
    {
        return $this->customizationOptions;
    }

    public function setCustomizationOptions(?array $customizationOptions): static
    {
        $this->customizationOptions = $customizationOptions;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function isPublic(): ?bool
    {
        return $this->isPublic;
    }

    public function setPublic(bool $isPublic): static
    {
        $this->isPublic = $isPublic;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function isVerified(): ?bool
    {
        return $this->isVerified;
    }

    public function setVerified(bool $isVerified): static
    {
        $this->isVerified = $isVerified;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getUsageCount(): ?int
    {
        return $this->usageCount;
    }

    public function setUsageCount(int $usageCount): static
    {
        $this->usageCount = $usageCount;
        return $this;
    }

    public function incrementUsageCount(): static
    {
        $this->usageCount++;
        return $this;
    }

    public function getAverageRating(): ?string
    {
        return $this->averageRating;
    }

    public function setAverageRating(?string $averageRating): static
    {
        $this->averageRating = $averageRating;
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

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    public function getRatings(): Collection
    {
        return $this->ratings;
    }

    public function addRating(TemplateRating $rating): static
    {
        if (!$this->ratings->contains($rating)) {
            $this->ratings->add($rating);
            $rating->setTemplate($this);
        }

        return $this;
    }

    public function removeRating(TemplateRating $rating): static
    {
        if ($this->ratings->removeElement($rating)) {
            if ($rating->getTemplate() === $this) {
                $rating->setTemplate(null);
            }
        }

        return $this;
    }

    public function calculateAverageRating(): void
    {
        $ratings = $this->getRatings();
        if ($ratings->isEmpty()) {
            $this->averageRating = null;
            return;
        }

        $sum = 0;
        foreach ($ratings as $rating) {
            $sum += $rating->getRating();
        }

        $this->averageRating = number_format($sum / $ratings->count(), 2);
    }
}