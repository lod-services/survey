<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'questions')]
class Question
{
    public const TYPE_TEXT = 'text';
    public const TYPE_TEXTAREA = 'textarea';
    public const TYPE_MULTIPLE_CHOICE = 'multiple_choice';
    public const TYPE_CHECKBOX = 'checkbox';
    public const TYPE_SCALE = 'scale';
    public const TYPE_EMAIL = 'email';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Survey::class, inversedBy: 'questions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Survey $survey = null;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank]
    private string $text = '';

    #[ORM\Column(type: 'string', length: 50)]
    #[Assert\Choice(choices: [
        self::TYPE_TEXT,
        self::TYPE_TEXTAREA,
        self::TYPE_MULTIPLE_CHOICE,
        self::TYPE_CHECKBOX,
        self::TYPE_SCALE,
        self::TYPE_EMAIL
    ])]
    private string $type = self::TYPE_TEXT;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $options = null;

    #[ORM\Column(type: 'integer')]
    #[Assert\PositiveOrZero]
    private int $position = 0;

    #[ORM\Column(type: 'boolean')]
    private bool $isRequired = true;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $helpText = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(type: 'boolean')]
    private bool $isAiGenerated = false;

    #[ORM\Column(type: 'decimal', precision: 3, scale: 2, nullable: true)]
    private ?float $aiConfidenceScore = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
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
        $this->updateTimestamp();
        return $this;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function setText(string $text): self
    {
        $this->text = $text;
        $this->updateTimestamp();
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        $this->updateTimestamp();
        return $this;
    }

    public function getOptions(): ?array
    {
        return $this->options;
    }

    public function setOptions(?array $options): self
    {
        $this->options = $options;
        $this->updateTimestamp();
        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = $position;
        $this->updateTimestamp();
        return $this;
    }

    public function isRequired(): bool
    {
        return $this->isRequired;
    }

    public function setIsRequired(bool $isRequired): self
    {
        $this->isRequired = $isRequired;
        $this->updateTimestamp();
        return $this;
    }

    public function getHelpText(): ?string
    {
        return $this->helpText;
    }

    public function setHelpText(?string $helpText): self
    {
        $this->helpText = $helpText;
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

    public function isAiGenerated(): bool
    {
        return $this->isAiGenerated;
    }

    public function setIsAiGenerated(bool $isAiGenerated): self
    {
        $this->isAiGenerated = $isAiGenerated;
        $this->updateTimestamp();
        return $this;
    }

    public function getAiConfidenceScore(): ?float
    {
        return $this->aiConfidenceScore;
    }

    public function setAiConfidenceScore(?float $aiConfidenceScore): self
    {
        $this->aiConfidenceScore = $aiConfidenceScore;
        $this->updateTimestamp();
        return $this;
    }

    private function updateTimestamp(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public static function getAvailableTypes(): array
    {
        return [
            self::TYPE_TEXT => 'Text Input',
            self::TYPE_TEXTAREA => 'Text Area',
            self::TYPE_MULTIPLE_CHOICE => 'Multiple Choice',
            self::TYPE_CHECKBOX => 'Checkbox',
            self::TYPE_SCALE => 'Scale (1-10)',
            self::TYPE_EMAIL => 'Email'
        ];
    }
}