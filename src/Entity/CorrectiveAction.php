<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\Efficacy;
use App\Repository\CorrectiveActionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A corrective action (PC.10.0 §4.3) belonging to a {@see NonConformity}'s action plan (PAC).
 *
 * Covers the procedure's lifecycle: identification (description, responsible, planned date),
 * optional Direction authorisation (required in the cases listed in §4.3.2), application
 * (implementation evidence) and the effectiveness review that supports closing the
 * non-conformity.
 */
#[ORM\Entity(repositoryClass: CorrectiveActionRepository::class)]
#[ORM\Table(name: 'corrective_action')]
#[ORM\UniqueConstraint(name: 'uniq_ca_nc_sequence', columns: ['non_conformity_id', 'sequence'])]
#[ORM\HasLifecycleCallbacks]
class CorrectiveAction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: NonConformity::class, inversedBy: 'correctiveActions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private NonConformity $nonConformity;

    /**
     * Sequential number within the parent non-conformity, starting at 1. Drives the reference.
     */
    #[ORM\Column]
    private int $sequence;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    private string $description;

    /**
     * Person responsible for implementing the action ("Responsable de su implantación").
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $responsible = null;

    /**
     * Planned implementation date ("Fecha de su implantación").
     */
    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $plannedDate = null;

    /**
     * Evidence/records of the implementation ("Documentos/registros de la implantación").
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $implementationEvidence = null;

    /**
     * Whether this action needs Direction authorisation (one of the PC.10.0 §4.3.2 cases, e.g.
     * new resources, changes to documentation in force or to several processes).
     */
    #[ORM\Column]
    private bool $requiresDirectionAuthorization = false;

    /**
     * Who authorised the action (when authorisation was required). The date is stamped together.
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $authorizedBy = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $authorizedAt = null;

    /**
     * Who reviewed the effectiveness ("Responsable de la revisión", the RSGMA in practice).
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $reviewedBy = null;

    /**
     * Effective date of the effectiveness review ("Fecha de la revisión").
     */
    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $reviewedAt = null;

    /**
     * Outcome of the effectiveness review; null while pending.
     */
    #[ORM\Column(length: 10, nullable: true, enumType: Efficacy::class)]
    private ?Efficacy $efficacy = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * Human reference within the non-conformity, e.g. "AC.01" (derived from the sequence).
     *
     * @return string the corrective action reference
     */
    public function getReference(): string
    {
        return sprintf('AC.%02d', $this->sequence);
    }

    /**
     * Whether the effectiveness review has been recorded (regardless of its outcome).
     *
     * @return bool true once an efficacy result is set
     */
    public function isReviewed(): bool
    {
        return null !== $this->efficacy;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNonConformity(): NonConformity
    {
        return $this->nonConformity;
    }

    public function setNonConformity(NonConformity $nonConformity): static
    {
        $this->nonConformity = $nonConformity;

        return $this;
    }

    public function getSequence(): int
    {
        return $this->sequence;
    }

    public function setSequence(int $sequence): static
    {
        $this->sequence = $sequence;

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

    public function getResponsible(): ?User
    {
        return $this->responsible;
    }

    public function setResponsible(?User $responsible): static
    {
        $this->responsible = $responsible;

        return $this;
    }

    public function getPlannedDate(): ?\DateTimeImmutable
    {
        return $this->plannedDate;
    }

    public function setPlannedDate(?\DateTimeImmutable $plannedDate): static
    {
        $this->plannedDate = $plannedDate;

        return $this;
    }

    public function getImplementationEvidence(): ?string
    {
        return $this->implementationEvidence;
    }

    public function setImplementationEvidence(?string $implementationEvidence): static
    {
        $this->implementationEvidence = $implementationEvidence;

        return $this;
    }

    public function requiresDirectionAuthorization(): bool
    {
        return $this->requiresDirectionAuthorization;
    }

    public function setRequiresDirectionAuthorization(bool $requiresDirectionAuthorization): static
    {
        $this->requiresDirectionAuthorization = $requiresDirectionAuthorization;

        return $this;
    }

    public function getAuthorizedBy(): ?User
    {
        return $this->authorizedBy;
    }

    public function setAuthorizedBy(?User $authorizedBy): static
    {
        $this->authorizedBy = $authorizedBy;

        return $this;
    }

    public function getAuthorizedAt(): ?\DateTimeImmutable
    {
        return $this->authorizedAt;
    }

    public function setAuthorizedAt(?\DateTimeImmutable $authorizedAt): static
    {
        $this->authorizedAt = $authorizedAt;

        return $this;
    }

    public function getReviewedBy(): ?User
    {
        return $this->reviewedBy;
    }

    public function setReviewedBy(?User $reviewedBy): static
    {
        $this->reviewedBy = $reviewedBy;

        return $this;
    }

    public function getReviewedAt(): ?\DateTimeImmutable
    {
        return $this->reviewedAt;
    }

    public function setReviewedAt(?\DateTimeImmutable $reviewedAt): static
    {
        $this->reviewedAt = $reviewedAt;

        return $this;
    }

    public function getEfficacy(): ?Efficacy
    {
        return $this->efficacy;
    }

    public function setEfficacy(?Efficacy $efficacy): static
    {
        $this->efficacy = $efficacy;

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
}
