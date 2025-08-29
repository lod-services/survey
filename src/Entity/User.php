<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[UniqueEntity(fields: ['email'], message: 'There is already an account with this email')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private ?string $email = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastActiveAt = null;

    #[ORM\Column]
    private int $totalPoints = 0;

    #[ORM\Column(nullable: true)]
    private ?string $timezone = null;

    /**
     * @var Collection<int, SurveyResponse>
     */
    #[ORM\OneToMany(targetEntity: SurveyResponse::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $surveyResponses;

    /**
     * @var Collection<int, UserAchievement>
     */
    #[ORM\OneToMany(targetEntity: UserAchievement::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $achievements;

    /**
     * @var Collection<int, UserPoint>
     */
    #[ORM\OneToMany(targetEntity: UserPoint::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $points;

    #[ORM\OneToOne(targetEntity: UserStreak::class, mappedBy: 'user', cascade: ['persist', 'remove'])]
    private ?UserStreak $streak = null;

    public function __construct()
    {
        $this->surveyResponses = new ArrayCollection();
        $this->achievements = new ArrayCollection();
        $this->points = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     *
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
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

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getLastActiveAt(): ?\DateTimeImmutable
    {
        return $this->lastActiveAt;
    }

    public function setLastActiveAt(?\DateTimeImmutable $lastActiveAt): static
    {
        $this->lastActiveAt = $lastActiveAt;

        return $this;
    }

    public function getTotalPoints(): int
    {
        return $this->totalPoints;
    }

    public function setTotalPoints(int $totalPoints): static
    {
        $this->totalPoints = $totalPoints;

        return $this;
    }

    public function addPoints(int $points): static
    {
        $this->totalPoints += $points;

        return $this;
    }

    public function getTimezone(): ?string
    {
        return $this->timezone;
    }

    public function setTimezone(?string $timezone): static
    {
        $this->timezone = $timezone;

        return $this;
    }

    /**
     * @return Collection<int, SurveyResponse>
     */
    public function getSurveyResponses(): Collection
    {
        return $this->surveyResponses;
    }

    public function addSurveyResponse(SurveyResponse $surveyResponse): static
    {
        if (!$this->surveyResponses->contains($surveyResponse)) {
            $this->surveyResponses->add($surveyResponse);
            $surveyResponse->setUser($this);
        }

        return $this;
    }

    public function removeSurveyResponse(SurveyResponse $surveyResponse): static
    {
        if ($this->surveyResponses->removeElement($surveyResponse)) {
            // set the owning side to null (unless already changed)
            if ($surveyResponse->getUser() === $this) {
                $surveyResponse->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, UserAchievement>
     */
    public function getAchievements(): Collection
    {
        return $this->achievements;
    }

    public function addAchievement(UserAchievement $achievement): static
    {
        if (!$this->achievements->contains($achievement)) {
            $this->achievements->add($achievement);
            $achievement->setUser($this);
        }

        return $this;
    }

    public function removeAchievement(UserAchievement $achievement): static
    {
        if ($this->achievements->removeElement($achievement)) {
            if ($achievement->getUser() === $this) {
                $achievement->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, UserPoint>
     */
    public function getPoints(): Collection
    {
        return $this->points;
    }

    public function addPoint(UserPoint $point): static
    {
        if (!$this->points->contains($point)) {
            $this->points->add($point);
            $point->setUser($this);
        }

        return $this;
    }

    public function removePoint(UserPoint $point): static
    {
        if ($this->points->removeElement($point)) {
            if ($point->getUser() === $this) {
                $point->setUser(null);
            }
        }

        return $this;
    }

    public function getStreak(): ?UserStreak
    {
        return $this->streak;
    }

    public function setStreak(UserStreak $streak): static
    {
        // set the owning side of the relation if necessary
        if ($streak->getUser() !== $this) {
            $streak->setUser($this);
        }

        $this->streak = $streak;

        return $this;
    }
}