<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private ?string $email = null;

    #[ORM\Column(length: 100)]
    private ?string $name = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $organization = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $industry = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $organizationSize = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $location = null;

    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\OneToMany(targetEntity: Template::class, mappedBy: 'createdBy')]
    private Collection $templates;

    #[ORM\OneToMany(targetEntity: TemplateRating::class, mappedBy: 'user')]
    private Collection $templateRatings;

    public function __construct()
    {
        $this->templates = new ArrayCollection();
        $this->templateRatings = new ArrayCollection();
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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getOrganization(): ?string
    {
        return $this->organization;
    }

    public function setOrganization(?string $organization): static
    {
        $this->organization = $organization;
        return $this;
    }

    public function getIndustry(): ?string
    {
        return $this->industry;
    }

    public function setIndustry(?string $industry): static
    {
        $this->industry = $industry;
        return $this;
    }

    public function getOrganizationSize(): ?string
    {
        return $this->organizationSize;
    }

    public function setOrganizationSize(?string $organizationSize): static
    {
        $this->organizationSize = $organizationSize;
        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): static
    {
        $this->location = $location;
        return $this;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;
        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
    }

    public function eraseCredentials(): void
    {
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

    public function getTemplates(): Collection
    {
        return $this->templates;
    }

    public function addTemplate(Template $template): static
    {
        if (!$this->templates->contains($template)) {
            $this->templates->add($template);
            $template->setCreatedBy($this);
        }

        return $this;
    }

    public function removeTemplate(Template $template): static
    {
        if ($this->templates->removeElement($template)) {
            if ($template->getCreatedBy() === $this) {
                $template->setCreatedBy(null);
            }
        }

        return $this;
    }

    public function getTemplateRatings(): Collection
    {
        return $this->templateRatings;
    }

    public function addTemplateRating(TemplateRating $templateRating): static
    {
        if (!$this->templateRatings->contains($templateRating)) {
            $this->templateRatings->add($templateRating);
            $templateRating->setUser($this);
        }

        return $this;
    }

    public function removeTemplateRating(TemplateRating $templateRating): static
    {
        if ($this->templateRatings->removeElement($templateRating)) {
            if ($templateRating->getUser() === $this) {
                $templateRating->setUser(null);
            }
        }

        return $this;
    }
}