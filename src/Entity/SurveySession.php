<?php

namespace App\Entity;

use App\Repository\SurveySessionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SurveySessionRepository::class)]
#[ORM\Index(name: 'idx_session_token', columns: ['session_token'])]
#[ORM\Index(name: 'idx_survey_activity', columns: ['survey_id', 'last_activity'])]
class SurveySession
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Survey::class, inversedBy: 'sessions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Survey $survey = null;

    #[ORM\Column(length: 64, unique: true)]
    private ?string $sessionToken = null;

    #[ORM\ManyToOne(targetEntity: Question::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Question $currentQuestion = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $progressData = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $completed = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $lastActivity = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $completedAt = null;

    #[ORM\OneToMany(targetEntity: Response::class, mappedBy: 'session', orphanRemoval: true)]
    private Collection $responses;

    public function __construct()
    {
        $this->responses = new ArrayCollection();
        $this->createdAt = new \DateTime();
        $this->lastActivity = new \DateTime();
        $this->sessionToken = bin2hex(random_bytes(32));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSurvey(): ?Survey
    {
        return $this->survey;
    }

    public function setSurvey(?Survey $survey): static
    {
        $this->survey = $survey;
        return $this;
    }

    public function getSessionToken(): ?string
    {
        return $this->sessionToken;
    }

    public function setSessionToken(string $sessionToken): static
    {
        $this->sessionToken = $sessionToken;
        return $this;
    }

    public function getCurrentQuestion(): ?Question
    {
        return $this->currentQuestion;
    }

    public function setCurrentQuestion(?Question $currentQuestion): static
    {
        $this->currentQuestion = $currentQuestion;
        return $this;
    }

    public function getProgressData(): ?array
    {
        return $this->progressData;
    }

    public function setProgressData(?array $progressData): static
    {
        $this->progressData = $progressData;
        return $this;
    }

    public function isCompleted(): bool
    {
        return $this->completed;
    }

    public function setCompleted(bool $completed): static
    {
        $this->completed = $completed;
        if ($completed && !$this->completedAt) {
            $this->completedAt = new \DateTime();
        }
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

    public function getLastActivity(): ?\DateTimeInterface
    {
        return $this->lastActivity;
    }

    public function setLastActivity(\DateTimeInterface $lastActivity): static
    {
        $this->lastActivity = $lastActivity;
        return $this;
    }

    public function getCompletedAt(): ?\DateTimeInterface
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTimeInterface $completedAt): static
    {
        $this->completedAt = $completedAt;
        return $this;
    }

    public function getResponses(): Collection
    {
        return $this->responses;
    }

    public function addResponse(Response $response): static
    {
        if (!$this->responses->contains($response)) {
            $this->responses->add($response);
            $response->setSession($this);
        }
        return $this;
    }

    public function removeResponse(Response $response): static
    {
        if ($this->responses->removeElement($response)) {
            if ($response->getSession() === $this) {
                $response->setSession(null);
            }
        }
        return $this;
    }

    public function updateActivity(): static
    {
        $this->lastActivity = new \DateTime();
        return $this;
    }
}