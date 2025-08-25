<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: 'App\Repository\QuestionRepository')]
#[ORM\Table(name: 'questions')]
#[ORM\HasLifecycleCallbacks]
class Question
{
    public const TYPE_TEXT = 'text';
    public const TYPE_TEXTAREA = 'textarea';
    public const TYPE_SINGLE_CHOICE = 'single_choice';
    public const TYPE_MULTIPLE_CHOICE = 'multiple_choice';
    public const TYPE_RATING = 'rating';
    public const TYPE_BOOLEAN = 'boolean';
    public const TYPE_DATE = 'date';
    public const TYPE_NUMBER = 'number';

    public const VALID_TYPES = [
        self::TYPE_TEXT,
        self::TYPE_TEXTAREA,
        self::TYPE_SINGLE_CHOICE,
        self::TYPE_MULTIPLE_CHOICE,
        self::TYPE_RATING,
        self::TYPE_BOOLEAN,
        self::TYPE_DATE,
        self::TYPE_NUMBER,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 500)]
    #[Assert\NotBlank(message: 'Question text cannot be blank')]
    #[Assert\Length(
        min: 5,
        max: 500,
        minMessage: 'Question must be at least {{ limit }} characters long',
        maxMessage: 'Question cannot be longer than {{ limit }} characters'
    )]
    private ?string $text = null;

    #[ORM\Column(type: 'string', length: 50)]
    #[Assert\NotBlank(message: 'Question type is required')]
    #[Assert\Choice(
        choices: Question::VALID_TYPES,
        message: 'Choose a valid question type'
    )]
    private ?string $type = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isRequired = false;

    #[ORM\Column(type: 'integer')]
    #[Assert\Range(
        min: 0,
        max: 1000,
        notInRangeMessage: 'Sort order must be between {{ min }} and {{ max }}'
    )]
    private int $sortOrder = 0;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $options = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $validation = null;

    #[ORM\Column(type: 'string', length: 1000, nullable: true)]
    #[Assert\Length(
        max: 1000,
        maxMessage: 'Help text cannot be longer than {{ limit }} characters'
    )]
    private ?string $helpText = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    #[ORM\ManyToOne(targetEntity: Survey::class, inversedBy: 'questions')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Question must belong to a survey')]
    private ?Survey $survey = null;

    #[ORM\OneToMany(mappedBy: 'question', targetEntity: Response::class)]
    private Collection $responses;

    public function __construct()
    {
        $this->responses = new ArrayCollection();
        $this->options = [];
        $this->validation = [];
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTimeImmutable();
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

    public function getText(): ?string
    {
        return $this->text;
    }

    public function setText(string $text): static
    {
        $this->text = $text;
        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        if (!in_array($type, self::VALID_TYPES)) {
            throw new \InvalidArgumentException(sprintf('Invalid question type "%s"', $type));
        }
        $this->type = $type;
        return $this;
    }

    public function isRequired(): bool
    {
        return $this->isRequired;
    }

    public function setIsRequired(bool $isRequired): static
    {
        $this->isRequired = $isRequired;
        return $this;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): static
    {
        $this->sortOrder = $sortOrder;
        return $this;
    }

    public function getOptions(): ?array
    {
        return $this->options;
    }

    public function setOptions(?array $options): static
    {
        $this->options = $options;
        return $this;
    }

    public function getOption(string $key, mixed $default = null): mixed
    {
        return $this->options[$key] ?? $default;
    }

    public function setOption(string $key, mixed $value): static
    {
        if (!is_array($this->options)) {
            $this->options = [];
        }
        $this->options[$key] = $value;
        return $this;
    }

    public function getValidation(): ?array
    {
        return $this->validation;
    }

    public function setValidation(?array $validation): static
    {
        $this->validation = $validation;
        return $this;
    }

    public function getValidationRule(string $key, mixed $default = null): mixed
    {
        return $this->validation[$key] ?? $default;
    }

    public function setValidationRule(string $key, mixed $value): static
    {
        if (!is_array($this->validation)) {
            $this->validation = [];
        }
        $this->validation[$key] = $value;
        return $this;
    }

    public function getHelpText(): ?string
    {
        return $this->helpText;
    }

    public function setHelpText(?string $helpText): static
    {
        $this->helpText = $helpText;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?\DateTimeImmutable $deletedAt): static
    {
        $this->deletedAt = $deletedAt;
        return $this;
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    public function softDelete(): static
    {
        $this->deletedAt = new \DateTimeImmutable();
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

    /**
     * @return Collection<int, Response>
     */
    public function getResponses(): Collection
    {
        return $this->responses;
    }

    public function addResponse(Response $response): static
    {
        if (!$this->responses->contains($response)) {
            $this->responses->add($response);
            $response->setQuestion($this);
        }

        return $this;
    }

    public function removeResponse(Response $response): static
    {
        if ($this->responses->removeElement($response)) {
            if ($response->getQuestion() === $this) {
                $response->setQuestion(null);
            }
        }

        return $this;
    }

    public function getResponseCount(): int
    {
        return $this->responses->count();
    }

    public function hasChoices(): bool
    {
        return in_array($this->type, [self::TYPE_SINGLE_CHOICE, self::TYPE_MULTIPLE_CHOICE]);
    }

    public function getChoices(): array
    {
        if (!$this->hasChoices()) {
            return [];
        }
        return $this->getOption('choices', []);
    }

    public function setChoices(array $choices): static
    {
        if ($this->hasChoices()) {
            $this->setOption('choices', $choices);
        }
        return $this;
    }

    public function getRatingScale(): array
    {
        if ($this->type !== self::TYPE_RATING) {
            return ['min' => 1, 'max' => 5];
        }
        return [
            'min' => $this->getOption('min_rating', 1),
            'max' => $this->getOption('max_rating', 5)
        ];
    }

    public function setRatingScale(int $min, int $max): static
    {
        if ($this->type === self::TYPE_RATING) {
            $this->setOption('min_rating', $min);
            $this->setOption('max_rating', $max);
        }
        return $this;
    }
}