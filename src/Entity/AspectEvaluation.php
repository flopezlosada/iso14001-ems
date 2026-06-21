<?php

declare(strict_types=1);

namespace App\Entity;

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
     * Intensity score. Null is meaningful: for consumption/waste it means "no prior-year data",
     * which the calculator treats as 4 ("Media"); for discharges it is simply not used.
     */
    #[ORM\Column(type: Types::SMALLINT, nullable: true, enumType: ScoreLevel::class)]
    private ?ScoreLevel $intensity = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true, enumType: ScoreLevel::class)]
    private ?ScoreLevel $hazard = null;

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
