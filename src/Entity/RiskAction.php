<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * One action of the plan to address a {@see RiskAssessment} (PC.03.0 §5.3 — "one or more actions").
 *
 * Responsible, deadline and efficacy are free text on purpose: the real F.08.0 fills them with
 * heterogeneous values ("RESPO SGMA", "DIC", "ANUAL", "Realizada", "Sí"), so a stricter type would
 * lose data. The deadline is NOT a date for the same reason.
 */
#[ORM\Entity]
#[ORM\Table(name: 'risk_action')]
class RiskAction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: RiskAssessment::class, inversedBy: 'actions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private RiskAssessment $assessment;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    private string $description;

    /**
     * Person/role responsible for the action (free text, e.g. "RESPO SGMA").
     */
    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $responsible = null;

    /**
     * Deadline as free text (e.g. "DIC", "ANUAL"); not a date — see class note.
     */
    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $deadline = null;

    /**
     * Efficacy review as free text (e.g. "Realizada", "OK", "Sí"); null while pending.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $efficacy = null;

    /**
     * Date the efficacy was evaluated (the "FECHA" column); optional.
     */
    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $evaluatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAssessment(): RiskAssessment
    {
        return $this->assessment;
    }

    public function setAssessment(RiskAssessment $assessment): static
    {
        $this->assessment = $assessment;

        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getResponsible(): ?string
    {
        return $this->responsible;
    }

    public function setResponsible(?string $responsible): static
    {
        $this->responsible = $responsible;

        return $this;
    }

    public function getDeadline(): ?string
    {
        return $this->deadline;
    }

    public function setDeadline(?string $deadline): static
    {
        $this->deadline = $deadline;

        return $this;
    }

    public function getEfficacy(): ?string
    {
        return $this->efficacy;
    }

    public function setEfficacy(?string $efficacy): static
    {
        $this->efficacy = $efficacy;

        return $this;
    }

    public function getEvaluatedAt(): ?\DateTimeImmutable
    {
        return $this->evaluatedAt;
    }

    public function setEvaluatedAt(?\DateTimeImmutable $evaluatedAt): static
    {
        $this->evaluatedAt = $evaluatedAt;

        return $this;
    }
}
