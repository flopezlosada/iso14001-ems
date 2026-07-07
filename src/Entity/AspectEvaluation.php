<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\AspectSignificanceStatus;
use App\Enum\InfluenceLevel;
use App\Enum\ScoreLevel;
use App\Repository\AspectEvaluationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * One year's evaluation of a direct {@see EnvironmentalAspect} (PG-06.01 Anexo I). Significance is
 * the sum of the criteria scores (frequency + intensity + hazard); intensity does not apply to
 * discharges. The computed {@see $significanceScore} and {@see $significant} flag are filled by
 * {@see \App\Service\AspectSignificanceCalculator} on save (single source of the rule).
 */
#[ORM\Entity(repositoryClass: AspectEvaluationRepository::class)]
#[ORM\Table(name: 'aspect_evaluation')]
#[ORM\UniqueConstraint(name: 'uniq_aspect_eval_year', columns: ['aspect_id', 'evaluation_year'])]
#[ORM\HasLifecycleCallbacks]
class AspectEvaluation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: EnvironmentalAspect::class, inversedBy: 'evaluations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private EnvironmentalAspect $aspect;

    #[ORM\Column(name: 'evaluation_year')]
    #[Assert\Range(min: 2000, max: 2100)]
    private int $year;

    #[ORM\Column(type: Types::SMALLINT, nullable: true, enumType: ScoreLevel::class)]
    private ?ScoreLevel $frequency = null;

    /**
     * Intensity score, scored for every direct category (discharges included since RG-06.01.01
     * Rev 02). Null is meaningful: it means "no prior-year data", which the calculator treats as
     * 4 ("Media").
     */
    #[ORM\Column(type: Types::SMALLINT, nullable: true, enumType: ScoreLevel::class)]
    private ?ScoreLevel $intensity = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true, enumType: ScoreLevel::class)]
    private ?ScoreLevel $hazard = null;

    // --- Criteria for abnormal aspects (PG-06.01 Anexo III), each scored 2/4/6 ---

    #[ORM\Column(type: Types::SMALLINT, nullable: true, enumType: ScoreLevel::class)]
    private ?ScoreLevel $probability = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true, enumType: ScoreLevel::class)]
    private ?ScoreLevel $control = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true, enumType: ScoreLevel::class)]
    private ?ScoreLevel $severity = null;

    /**
     * Capacity-of-influence score for indirect aspects (PG-06.01 Anexo II), 1/2/3.
     */
    #[ORM\Column(type: Types::SMALLINT, nullable: true, enumType: InfluenceLevel::class)]
    private ?InfluenceLevel $influence = null;

    /**
     * Computed significance sum. Maintained by the calculator on save; not edited directly.
     */
    #[ORM\Column]
    private int $significanceScore = 0;

    /**
     * Whether the aspect is significant this year (computed: score above the threshold).
     */
    #[ORM\Column]
    private bool $significant = false;

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

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAspect(): EnvironmentalAspect
    {
        return $this->aspect;
    }

    public function setAspect(EnvironmentalAspect $aspect): static
    {
        $this->aspect = $aspect;

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

    public function getFrequency(): ?ScoreLevel
    {
        return $this->frequency;
    }

    public function setFrequency(?ScoreLevel $frequency): static
    {
        $this->frequency = $frequency;

        return $this;
    }

    public function getIntensity(): ?ScoreLevel
    {
        return $this->intensity;
    }

    public function setIntensity(?ScoreLevel $intensity): static
    {
        $this->intensity = $intensity;

        return $this;
    }

    public function getHazard(): ?ScoreLevel
    {
        return $this->hazard;
    }

    public function setHazard(?ScoreLevel $hazard): static
    {
        $this->hazard = $hazard;

        return $this;
    }

    public function getProbability(): ?ScoreLevel
    {
        return $this->probability;
    }

    public function setProbability(?ScoreLevel $probability): static
    {
        $this->probability = $probability;

        return $this;
    }

    public function getControl(): ?ScoreLevel
    {
        return $this->control;
    }

    public function setControl(?ScoreLevel $control): static
    {
        $this->control = $control;

        return $this;
    }

    public function getSeverity(): ?ScoreLevel
    {
        return $this->severity;
    }

    public function setSeverity(?ScoreLevel $severity): static
    {
        $this->severity = $severity;

        return $this;
    }

    public function getInfluence(): ?InfluenceLevel
    {
        return $this->influence;
    }

    public function setInfluence(?InfluenceLevel $influence): static
    {
        $this->influence = $influence;

        return $this;
    }

    public function getSignificanceScore(): int
    {
        return $this->significanceScore;
    }

    public function setSignificanceScore(int $significanceScore): static
    {
        $this->significanceScore = $significanceScore;

        return $this;
    }

    public function isSignificant(): bool
    {
        return $this->significant;
    }

    /**
     * The significance status of this evaluation (significant / non-significant) on the shared
     * semantic scale, so its result reads with the same colour everywhere it is shown.
     *
     * @return AspectSignificanceStatus the status of this evaluation (never UNEVALUATED)
     */
    public function getSignificanceStatus(): AspectSignificanceStatus
    {
        return AspectSignificanceStatus::forEvaluation($this);
    }

    public function setSignificant(bool $significant): static
    {
        $this->significant = $significant;

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
