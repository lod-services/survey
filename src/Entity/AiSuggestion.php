<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'ai_suggestions')]
class AiSuggestion
{
    public const TYPE_QUESTION = 'question';
    public const TYPE_BIAS_DETECTION = 'bias_detection';
    public const TYPE_FLOW_OPTIMIZATION = 'flow_optimization';
    public const TYPE_LANGUAGE_ENHANCEMENT = 'language_enhancement';

    public const ACTION_PENDING = 'pending';
    public const ACTION_ACCEPTED = 'accepted';
    public const ACTION_REJECTED = 'rejected';
    public const ACTION_MODIFIED = 'modified';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Survey::class, inversedBy: 'aiSuggestions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Survey $survey = null;

    #[ORM\Column(type: 'string', length: 50)]
    #[Assert\Choice(choices: [
        self::TYPE_QUESTION,
        self::TYPE_BIAS_DETECTION,
        self::TYPE_FLOW_OPTIMIZATION,
        self::TYPE_LANGUAGE_ENHANCEMENT
    ])]
    private string $suggestionType = self::TYPE_QUESTION;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $originalContent = null;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank]
    private string $suggestedContent = '';

    #[ORM\Column(type: 'decimal', precision: 3, scale: 2)]
    #[Assert\Range(min: 0.00, max: 1.00)]
    private float $confidenceScore = 0.00;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $rationale = null;

    #[ORM\Column(type: 'string', length: 20)]
    #[Assert\Choice(choices: [
        self::ACTION_PENDING,
        self::ACTION_ACCEPTED,
        self::ACTION_REJECTED,
        self::ACTION_MODIFIED
    ])]
    private string $userAction = self::ACTION_PENDING;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $metadata = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $appliedAt = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Assert\Range(min: 1, max: 5)]
    private ?int $userRating = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $userFeedback = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSurvey(): ?Survey
    {
        return $this->survey;
    }

    public function setSurvey(?Survey $survey): self
    {
        $this->survey = $survey;
        return $this;
    }

    public function getSuggestionType(): string
    {
        return $this->suggestionType;
    }

    public function setSuggestionType(string $suggestionType): self
    {
        $this->suggestionType = $suggestionType;
        return $this;
    }

    public function getOriginalContent(): ?string
    {
        return $this->originalContent;
    }

    public function setOriginalContent(?string $originalContent): self
    {
        $this->originalContent = $originalContent;
        return $this;
    }

    public function getSuggestedContent(): string
    {
        return $this->suggestedContent;
    }

    public function setSuggestedContent(string $suggestedContent): self
    {
        $this->suggestedContent = $suggestedContent;
        return $this;
    }

    public function getConfidenceScore(): float
    {
        return $this->confidenceScore;
    }

    public function setConfidenceScore(float $confidenceScore): self
    {
        $this->confidenceScore = $confidenceScore;
        return $this;
    }

    public function getRationale(): ?string
    {
        return $this->rationale;
    }

    public function setRationale(?string $rationale): self
    {
        $this->rationale = $rationale;
        return $this;
    }

    public function getUserAction(): string
    {
        return $this->userAction;
    }

    public function setUserAction(string $userAction): self
    {
        $this->userAction = $userAction;
        if ($userAction !== self::ACTION_PENDING && $this->appliedAt === null) {
            $this->appliedAt = new \DateTimeImmutable();
        }
        return $this;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): self
    {
        $this->metadata = $metadata;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getAppliedAt(): ?\DateTimeImmutable
    {
        return $this->appliedAt;
    }

    public function setAppliedAt(?\DateTimeImmutable $appliedAt): self
    {
        $this->appliedAt = $appliedAt;
        return $this;
    }

    public function getUserRating(): ?int
    {
        return $this->userRating;
    }

    public function setUserRating(?int $userRating): self
    {
        $this->userRating = $userRating;
        return $this;
    }

    public function getUserFeedback(): ?string
    {
        return $this->userFeedback;
    }

    public function setUserFeedback(?string $userFeedback): self
    {
        $this->userFeedback = $userFeedback;
        return $this;
    }

    public function isPending(): bool
    {
        return $this->userAction === self::ACTION_PENDING;
    }

    public function isAccepted(): bool
    {
        return $this->userAction === self::ACTION_ACCEPTED;
    }

    public function isRejected(): bool
    {
        return $this->userAction === self::ACTION_REJECTED;
    }

    public function isModified(): bool
    {
        return $this->userAction === self::ACTION_MODIFIED;
    }

    public static function getAvailableTypes(): array
    {
        return [
            self::TYPE_QUESTION => 'Question Generation',
            self::TYPE_BIAS_DETECTION => 'Bias Detection',
            self::TYPE_FLOW_OPTIMIZATION => 'Flow Optimization',
            self::TYPE_LANGUAGE_ENHANCEMENT => 'Language Enhancement'
        ];
    }

    public static function getAvailableActions(): array
    {
        return [
            self::ACTION_PENDING => 'Pending',
            self::ACTION_ACCEPTED => 'Accepted',
            self::ACTION_REJECTED => 'Rejected',
            self::ACTION_MODIFIED => 'Modified'
        ];
    }
}