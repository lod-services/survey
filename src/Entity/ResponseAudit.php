<?php

namespace App\Entity;

use App\Repository\ResponseAuditRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ResponseAuditRepository::class)]
#[ORM\Index(name: 'idx_response_timestamp', columns: ['response_id', 'timestamp'])]
#[ORM\Index(name: 'idx_rule_id', columns: ['rule_id'])]
class ResponseAudit
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Response::class, inversedBy: 'auditLogs')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Response $response = null;

    #[ORM\ManyToOne(targetEntity: SurveyRule::class, inversedBy: 'auditLogs')]
    #[ORM\JoinColumn(nullable: false)]
    private ?SurveyRule $rule = null;

    #[ORM\Column(type: Types::JSON)]
    private array $evaluationResult = [];

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $timestamp = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    public function __construct()
    {
        $this->timestamp = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getResponse(): ?Response
    {
        return $this->response;
    }

    public function setResponse(?Response $response): static
    {
        $this->response = $response;
        return $this;
    }

    public function getRule(): ?SurveyRule
    {
        return $this->rule;
    }

    public function setRule(?SurveyRule $rule): static
    {
        $this->rule = $rule;
        return $this;
    }

    public function getEvaluationResult(): array
    {
        return $this->evaluationResult;
    }

    public function setEvaluationResult(array $evaluationResult): static
    {
        $this->evaluationResult = $evaluationResult;
        return $this;
    }

    public function getTimestamp(): ?\DateTimeInterface
    {
        return $this->timestamp;
    }

    public function setTimestamp(\DateTimeInterface $timestamp): static
    {
        $this->timestamp = $timestamp;
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;
        return $this;
    }
}