<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\DafoAnalysisRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * SWOT analysis of the centre's environmental context for a school year (register "F.06.0 DAFO",
 * ISO 14001 clause 4.1). The source document is a free-text 2x2 matrix, so each quadrant is a
 * single text field: weaknesses/threats are the negative side (internal/external) and
 * strengths/opportunities the positive one. There is one analysis per school year.
 */
#[ORM\Entity(repositoryClass: DafoAnalysisRepository::class)]
#[ORM\Table(name: 'dafo_analysis')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['schoolYear'], message: 'Ya existe un análisis DAFO para ese ejercicio.')]
class DafoAnalysis
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * School year the analysis belongs to, as text (e.g. "2025-2026"); the period key, unique.
     */
    #[ORM\Column(length: 9, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^\d{4}-\d{4}$/', message: 'Usa el formato de curso escolar, p. ej. 2025-2026.')]
    private string $schoolYear;

    /**
     * Weaknesses quadrant (internal, negative); free text, one item per line. Null while empty.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $weaknesses = null;

    /**
     * Threats quadrant (external, negative); free text, one item per line. Null while empty.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $threats = null;

    /**
     * Strengths quadrant (internal, positive); free text, one item per line. Null while empty.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $strengths = null;

    /**
     * Opportunities quadrant (external, positive); free text, one item per line. Null while empty.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $opportunities = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    /**
     * Refreshes the update timestamp on every persist/update.
     */
    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSchoolYear(): string
    {
        return $this->schoolYear;
    }

    public function setSchoolYear(string $schoolYear): static
    {
        $this->schoolYear = $schoolYear;

        return $this;
    }

    public function getWeaknesses(): ?string
    {
        return $this->weaknesses;
    }

    public function setWeaknesses(?string $weaknesses): static
    {
        $this->weaknesses = $weaknesses;

        return $this;
    }

    public function getThreats(): ?string
    {
        return $this->threats;
    }

    public function setThreats(?string $threats): static
    {
        $this->threats = $threats;

        return $this;
    }

    public function getStrengths(): ?string
    {
        return $this->strengths;
    }

    public function setStrengths(?string $strengths): static
    {
        $this->strengths = $strengths;

        return $this;
    }

    public function getOpportunities(): ?string
    {
        return $this->opportunities;
    }

    public function setOpportunities(?string $opportunities): static
    {
        $this->opportunities = $opportunities;

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
