<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\SupplierCriterion;
use App\Repository\SupplierEvaluationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * One year's evaluation of a {@see Supplier} (PC.05 §5.6, annual re-evaluation). The approval
 * status is not stored: it is derived from the {@see SupplierCriterion} to keep a single source
 * of truth.
 */
#[ORM\Entity(repositoryClass: SupplierEvaluationRepository::class)]
#[ORM\Table(name: 'supplier_evaluation')]
#[ORM\UniqueConstraint(name: 'uniq_supplier_eval_year', columns: ['supplier_id', 'evaluation_year'])]
#[ORM\HasLifecycleCallbacks]
class SupplierEvaluation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Supplier::class, inversedBy: 'evaluations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Supplier $supplier;

    #[ORM\Column(name: 'evaluation_year')]
    #[Assert\Range(min: 2000, max: 2100)]
    private int $year;

    #[ORM\Column(length: 20, enumType: SupplierCriterion::class)]
    private SupplierCriterion $criterion;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

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
     * Whether the supplier is approved for this evaluation (derived from the criterion).
     */
    public function isApproved(): bool
    {
        return $this->criterion->isApproved();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSupplier(): Supplier
    {
        return $this->supplier;
    }

    public function setSupplier(Supplier $supplier): static
    {
        $this->supplier = $supplier;

        return $this;
    }

    public function getYear(): int
    {
        return $this->year;
    }

    public function setYear(int $year): static
    {
        $this->year = $year;

        return $this;
    }

    public function getCriterion(): SupplierCriterion
    {
        return $this->criterion;
    }

    public function setCriterion(SupplierCriterion $criterion): static
    {
        $this->criterion = $criterion;

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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
