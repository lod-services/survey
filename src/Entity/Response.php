<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: 'App\Repository\ResponseRepository')]
#[ORM\Table(name: 'responses')]
#[ORM\UniqueConstraint(name: 'unique_user_question_response', columns: ['user_id', 'question_id'])]
#[ORM\HasLifecycleCallbacks]
class Response
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(
        max: 5000,
        maxMessage: 'Response cannot be longer than {{ limit }} characters'
    )]
    private ?string $textValue = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $integerValue = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $floatValue = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $booleanValue = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $dateValue = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $arrayValue = null;

    #[ORM\Column(type: 'string', length: 50)]
    #[Assert\NotBlank(message: 'Response type is required')]
    private ?string $valueType = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $submittedAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $sessionId = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $metadata = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'responses')]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Survey::class, inversedBy: 'responses')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Response must belong to a survey')]
    private ?Survey $survey = null;

    #[ORM\ManyToOne(targetEntity: Question::class, inversedBy: 'responses')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Response must belong to a question')]
    private ?Question $question = null;

    public function __construct()
    {
        $this->metadata = [];
    }

    #[ORM\PrePersist]
    public function setSubmittedAtValue(): void
    {
        $this->submittedAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTextValue(): ?string
    {
        return $this->textValue;
    }

    public function setTextValue(?string $textValue): static
    {
        $this->textValue = $textValue;
        $this->valueType = 'text';
        return $this;
    }

    public function getIntegerValue(): ?int
    {
        return $this->integerValue;
    }

    public function setIntegerValue(?int $integerValue): static
    {
        $this->integerValue = $integerValue;
        $this->valueType = 'integer';
        return $this;
    }

    public function getFloatValue(): ?float
    {
        return $this->floatValue;
    }

    public function setFloatValue(?float $floatValue): static
    {
        $this->floatValue = $floatValue;
        $this->valueType = 'float';
        return $this;
    }

    public function getBooleanValue(): ?bool
    {
        return $this->booleanValue;
    }

    public function setBooleanValue(?bool $booleanValue): static
    {
        $this->booleanValue = $booleanValue;
        $this->valueType = 'boolean';
        return $this;
    }

    public function getDateValue(): ?\DateTimeImmutable
    {
        return $this->dateValue;
    }

    public function setDateValue(?\DateTimeImmutable $dateValue): static
    {
        $this->dateValue = $dateValue;
        $this->valueType = 'date';
        return $this;
    }

    public function getArrayValue(): ?array
    {
        return $this->arrayValue;
    }

    public function setArrayValue(?array $arrayValue): static
    {
        $this->arrayValue = $arrayValue;
        $this->valueType = 'array';
        return $this;
    }

    public function getValueType(): ?string
    {
        return $this->valueType;
    }

    public function setValueType(string $valueType): static
    {
        $this->valueType = $valueType;
        return $this;
    }

    public function getValue(): mixed
    {
        return match ($this->valueType) {
            'text' => $this->textValue,
            'integer' => $this->integerValue,
            'float' => $this->floatValue,
            'boolean' => $this->booleanValue,
            'date' => $this->dateValue,
            'array' => $this->arrayValue,
            default => null,
        };
    }

    public function setValue(mixed $value): static
    {
        match (true) {
            is_string($value) => $this->setTextValue($value),
            is_int($value) => $this->setIntegerValue($value),
            is_float($value) => $this->setFloatValue($value),
            is_bool($value) => $this->setBooleanValue($value),
            $value instanceof \DateTimeImmutable => $this->setDateValue($value),
            is_array($value) => $this->setArrayValue($value),
            default => throw new \InvalidArgumentException('Unsupported value type'),
        };
        return $this;
    }

    public function getSubmittedAt(): ?\DateTimeImmutable
    {
        return $this->submittedAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getSessionId(): ?string
    {
        return $this->sessionId;
    }

    public function setSessionId(?string $sessionId): static
    {
        $this->sessionId = $sessionId;
        return $this;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): static
    {
        $this->metadata = $metadata;
        return $this;
    }

    public function getMetadataValue(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    public function setMetadataValue(string $key, mixed $value): static
    {
        if (!is_array($this->metadata)) {
            $this->metadata = [];
        }
        $this->metadata[$key] = $value;
        return $this;
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

    public function getSurvey(): ?Survey
    {
        return $this->survey;
    }

    public function setSurvey(?Survey $survey): static
    {
        $this->survey = $survey;
        return $this;
    }

    public function getQuestion(): ?Question
    {
        return $this->question;
    }

    public function setQuestion(?Question $question): static
    {
        $this->question = $question;
        return $this;
    }

    public function isAnonymous(): bool
    {
        return $this->user === null;
    }

    public function isEmpty(): bool
    {
        return match ($this->valueType) {
            'text' => empty($this->textValue),
            'integer' => $this->integerValue === null,
            'float' => $this->floatValue === null,
            'boolean' => $this->booleanValue === null,
            'date' => $this->dateValue === null,
            'array' => empty($this->arrayValue),
            default => true,
        };
    }

    public function getDisplayValue(): string
    {
        if ($this->isEmpty()) {
            return '';
        }

        return match ($this->valueType) {
            'text' => $this->textValue,
            'integer' => (string) $this->integerValue,
            'float' => (string) $this->floatValue,
            'boolean' => $this->booleanValue ? 'Yes' : 'No',
            'date' => $this->dateValue->format('Y-m-d'),
            'array' => implode(', ', $this->arrayValue),
            default => '',
        };
    }
}