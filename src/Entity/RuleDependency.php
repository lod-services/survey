<?php

namespace App\Entity;

use App\Repository\RuleDependencyRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RuleDependencyRepository::class)]
#[ORM\Index(name: 'idx_parent_rule', columns: ['parent_rule_id'])]
#[ORM\Index(name: 'idx_child_rule', columns: ['child_rule_id'])]
#[ORM\UniqueConstraint(name: 'uniq_parent_child', columns: ['parent_rule_id', 'child_rule_id'])]
class RuleDependency
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: SurveyRule::class, inversedBy: 'childDependencies')]
    #[ORM\JoinColumn(nullable: false)]
    private ?SurveyRule $parentRule = null;

    #[ORM\ManyToOne(targetEntity: SurveyRule::class, inversedBy: 'parentDependencies')]
    #[ORM\JoinColumn(nullable: false)]
    private ?SurveyRule $childRule = null;

    #[ORM\Column(length: 50)]
    private ?string $dependencyType = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getParentRule(): ?SurveyRule
    {
        return $this->parentRule;
    }

    public function setParentRule(?SurveyRule $parentRule): static
    {
        $this->parentRule = $parentRule;
        return $this;
    }

    public function getChildRule(): ?SurveyRule
    {
        return $this->childRule;
    }

    public function setChildRule(?SurveyRule $childRule): static
    {
        $this->childRule = $childRule;
        return $this;
    }

    public function getDependencyType(): ?string
    {
        return $this->dependencyType;
    }

    public function setDependencyType(string $dependencyType): static
    {
        $this->dependencyType = $dependencyType;
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
}