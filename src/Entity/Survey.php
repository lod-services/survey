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

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $branchingEnabled = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\OneToMany(targetEntity: Question::class, mappedBy: 'survey', orphanRemoval: true)]
    private Collection $questions;

    #[ORM\OneToMany(targetEntity: SurveyRule::class, mappedBy: 'survey', orphanRemoval: true)]
    private Collection $rules;

    #[ORM\OneToMany(targetEntity: SurveySession::class, mappedBy: 'survey', orphanRemoval: true)]
    private Collection $sessions;

    public function __construct()
    {
        $this->questions = new ArrayCollection();
        $this->rules = new ArrayCollection();
        $this->sessions = new ArrayCollection();
        $this->createdAt = new \DateTime();
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

    public function isBranchingEnabled(): bool
    {
        return $this->branchingEnabled;
    }

    public function setBranchingEnabled(bool $branchingEnabled): static
    {
        $this->branchingEnabled = $branchingEnabled;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getQuestions(): Collection
    {
        return $this->questions;
    }

    public function addQuestion(Question $question): static
    {
        if (!$this->questions->contains($question)) {
            $this->questions->add($question);
            $question->setSurvey($this);
        }
        return $this;
    }

    public function removeQuestion(Question $question): static
    {
        if ($this->questions->removeElement($question)) {
            if ($question->getSurvey() === $this) {
                $question->setSurvey(null);
            }
        }
        return $this;
    }

    public function getRules(): Collection
    {
        return $this->rules;
    }

    public function addRule(SurveyRule $rule): static
    {
        if (!$this->rules->contains($rule)) {
            $this->rules->add($rule);
            $rule->setSurvey($this);
        }
        return $this;
    }

    public function removeRule(SurveyRule $rule): static
    {
        if ($this->rules->removeElement($rule)) {
            if ($rule->getSurvey() === $this) {
                $rule->setSurvey(null);
            }
        }
        return $this;
    }

    public function getSessions(): Collection
    {
        return $this->sessions;
    }

    public function addSession(SurveySession $session): static
    {
        if (!$this->sessions->contains($session)) {
            $this->sessions->add($session);
            $session->setSurvey($this);
        }
        return $this;
    }

    public function removeSession(SurveySession $session): static
    {
        if ($this->sessions->removeElement($session)) {
            if ($session->getSurvey() === $this) {
                $session->setSurvey(null);
            }
        }
        return $this;
    }
}
