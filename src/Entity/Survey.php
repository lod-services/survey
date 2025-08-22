<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'surveys')]
class Survey
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $title = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $objective = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $targetAudience = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(type: 'boolean')]
    private bool $isPublished = false;

    #[ORM\OneToMany(mappedBy: 'survey', targetEntity: Question::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $questions;

    #[ORM\OneToMany(mappedBy: 'survey', targetEntity: AiSuggestion::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $aiSuggestions;

    public function __construct()
    {
        $this->questions = new ArrayCollection();
        $this->aiSuggestions = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        $this->updateTimestamp();
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        $this->updateTimestamp();
        return $this;
    }

    public function getObjective(): ?string
    {
        return $this->objective;
    }

    public function setObjective(?string $objective): self
    {
        $this->objective = $objective;
        $this->updateTimestamp();
        return $this;
    }

    public function getTargetAudience(): ?string
    {
        return $this->targetAudience;
    }

    public function setTargetAudience(?string $targetAudience): self
    {
        $this->targetAudience = $targetAudience;
        $this->updateTimestamp();
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function isPublished(): bool
    {
        return $this->isPublished;
    }

    public function setIsPublished(bool $isPublished): self
    {
        $this->isPublished = $isPublished;
        $this->updateTimestamp();
        return $this;
    }

    /**
     * @return Collection<int, Question>
     */
    public function getQuestions(): Collection
    {
        return $this->questions;
    }

    public function addQuestion(Question $question): self
    {
        if (!$this->questions->contains($question)) {
            $this->questions->add($question);
            $question->setSurvey($this);
        }
        $this->updateTimestamp();
        return $this;
    }

    public function removeQuestion(Question $question): self
    {
        if ($this->questions->removeElement($question)) {
            if ($question->getSurvey() === $this) {
                $question->setSurvey(null);
            }
        }
        $this->updateTimestamp();
        return $this;
    }

    /**
     * @return Collection<int, AiSuggestion>
     */
    public function getAiSuggestions(): Collection
    {
        return $this->aiSuggestions;
    }

    public function addAiSuggestion(AiSuggestion $aiSuggestion): self
    {
        if (!$this->aiSuggestions->contains($aiSuggestion)) {
            $this->aiSuggestions->add($aiSuggestion);
            $aiSuggestion->setSurvey($this);
        }
        return $this;
    }

    public function removeAiSuggestion(AiSuggestion $aiSuggestion): self
    {
        if ($this->aiSuggestions->removeElement($aiSuggestion)) {
            if ($aiSuggestion->getSurvey() === $this) {
                $aiSuggestion->setSurvey(null);
            }
        }
        return $this;
    }

    private function updateTimestamp(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}