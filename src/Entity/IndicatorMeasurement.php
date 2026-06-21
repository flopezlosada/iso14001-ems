<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\IndicatorMeasurementRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A single periodic measurement of an {@see Indicator} (F.09.0 historical data). The
 * {@see $breached} flag is a manual judgement that the value transgresses the indicator's
 * reference value (a candidate for a non-conformity), since the reference is free text and the
 * "good" direction varies per indicator.
 */
#[ORM\Entity(repositoryClass: IndicatorMeasurementRepository::class)]
#[ORM\Table(name: 'indicator_measurement')]
#[ORM\UniqueConstraint(name: 'uniq_indicator_period', columns: ['indicator_id', 'period_year', 'period_month'])]
#[ORM\HasLifecycleCallbacks]
class IndicatorMeasurement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Indicator::class, inversedBy: 'measurements')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Indicator $indicator;

    #[ORM\Column(name: 'period_year')]
    #[Assert\Range(min: 2000, max: 2100)]
    private int $year;

    #[ORM\Column(name: 'period_month')]
    #[Assert\Range(min: 1, max: 12)]
    private int $month;

    /**
     * Measured value. Decimal mapped as string to avoid floating-point rounding.
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 3)]
    #[Assert\NotNull]
    #[Assert\Regex(pattern: '/^-?\d{1,10}(\.\d{1,3})?$/', message: 'Introduce un número válido (usa el punto, máx. 3 decimales).')]
    private string $value;

    /**
     * Manual judgement that this measurement transgresses the indicator's reference value.
     */
    #[ORM\Column]
    private bool $breached = false;

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

    public function getIndicator(): Indicator
    {
        return $this->indicator;
    }

    public function setIndicator(Indicator $indicator): static
    {
        $this->indicator = $indicator;

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

    public function getMonth(): int
    {
        return $this->month;
    }

    public function setMonth(int $month): static
    {
        $this->month = $month;

        return $this;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function setValue(string $value): static
    {
        $this->value = $value;

        return $this;
    }

    public function isBreached(): bool
    {
        return $this->breached;
    }

    public function setBreached(bool $breached): static
    {
        $this->breached = $breached;

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
