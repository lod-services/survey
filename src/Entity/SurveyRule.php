<?php

namespace App\Entity;

use App\Repository\SurveyRuleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SurveyRuleRepository::class)]
#[ORM\Index(name: 'idx_survey_priority', columns: ['survey_id', 'priority'])]
class SurveyRule
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Survey::class, inversedBy: 'rules')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Survey $survey = null;

    #[ORM\Column(type: Types::JSON)]
    private array $conditionJson = [];

    #[ORM\Column(type: Types::JSON)]
    private array $actionJson = [];

    #[ORM\Column]
    private ?int $priority = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $active = true;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\OneToMany(targetEntity: RuleDependency::class, mappedBy: 'parentRule', orphanRemoval: true)]
    private Collection $childDependencies;

    #[ORM\OneToMany(targetEntity: RuleDependency::class, mappedBy: 'childRule', orphanRemoval: true)]
    private Collection $parentDependencies;

    #[ORM\OneToMany(targetEntity: ResponseAudit::class, mappedBy: 'rule', orphanRemoval: true)]
    private Collection $auditLogs;

    public function __construct()
    {
        $this->childDependencies = new ArrayCollection();
        $this->parentDependencies = new ArrayCollection();
        $this->auditLogs = new ArrayCollection();
        $this->createdAt = new \DateTime();
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

    public function getConditionJson(): array
    {
        return $this->conditionJson;
    }

    public function setConditionJson(array $conditionJson): static
    {
        $this->conditionJson = $conditionJson;
        return $this;
    }

    public function getActionJson(): array
    {
        return $this->actionJson;
    }

    public function setActionJson(array $actionJson): static
    {
        $this->actionJson = $actionJson;
        return $this;
    }

    public function getPriority(): ?int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): static
    {
        $this->priority = $priority;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;
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

    public function getChildDependencies(): Collection
    {
        return $this->childDependencies;
    }

    public function addChildDependency(RuleDependency $childDependency): static
    {
        if (!$this->childDependencies->contains($childDependency)) {
            $this->childDependencies->add($childDependency);
            $childDependency->setParentRule($this);
        }
        return $this;
    }

    public function removeChildDependency(RuleDependency $childDependency): static
    {
        if ($this->childDependencies->removeElement($childDependency)) {
            if ($childDependency->getParentRule() === $this) {
                $childDependency->setParentRule(null);
            }
        }
        return $this;
    }

    public function getParentDependencies(): Collection
    {
        return $this->parentDependencies;
    }

    public function addParentDependency(RuleDependency $parentDependency): static
    {
        if (!$this->parentDependencies->contains($parentDependency)) {
            $this->parentDependencies->add($parentDependency);
            $parentDependency->setChildRule($this);
        }
        return $this;
    }

    public function removeParentDependency(RuleDependency $parentDependency): static
    {
        if ($this->parentDependencies->removeElement($parentDependency)) {
            if ($parentDependency->getChildRule() === $this) {
                $parentDependency->setChildRule(null);
            }
        }
        return $this;
    }

    public function getAuditLogs(): Collection
    {
        return $this->auditLogs;
    }

    public function addAuditLog(ResponseAudit $auditLog): static
    {
        if (!$this->auditLogs->contains($auditLog)) {
            $this->auditLogs->add($auditLog);
            $auditLog->setRule($this);
        }
        return $this;
    }

    public function removeAuditLog(ResponseAudit $auditLog): static
    {
        if ($this->auditLogs->removeElement($auditLog)) {
            if ($auditLog->getRule() === $this) {
                $auditLog->setRule(null);
            }
        }
        return $this;
    }
}