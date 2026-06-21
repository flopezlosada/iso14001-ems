<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ConsumptionType;
use App\Repository\ConsumptionReadingRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A single monthly consumption reading for one utility (form F-6.1.2).
 *
 * There is at most one reading per (type, year, month). Quantity is expressed in the unit of
 * the {@see ConsumptionType}; cost (euros) is only recorded for types that track it.
 */
#[ORM\Entity(repositoryClass: ConsumptionReadingRepository::class)]
#[ORM\Table(name: 'consumption_reading')]
#[ORM\UniqueConstraint(name: 'uniq_consumption_period', columns: ['type', 'period_year', 'period_month'])]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['type', 'periodYear', 'periodMonth'], message: 'There is already a reading for this utility and month.')]
class ConsumptionReading
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(enumType: ConsumptionType::class)]
    private ConsumptionType $type;

    #[ORM\Column(name: 'period_year')]
    #[Assert\Range(min: 2000, max: 2100)]
    private int $periodYear;

    #[ORM\Column(name: 'period_month')]
    #[Assert\Range(min: 1, max: 12)]
    private int $periodMonth;

    /**
     * Recorded quantity in the unit of {@see ConsumptionType::unit()}. Doctrine decimal is
     * mapped as string to avoid floating-point rounding.
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 3)]
    #[Assert\NotNull]
    #[Assert\PositiveOrZero]
    #[Assert\Regex(pattern: '/^\d{1,9}(\.\d{1,3})?$/', message: 'Introduce un número válido (usa el punto, máx. 3 decimales).')]
    private string $quantity;

    /**
     * Cost in euros, only for types where {@see ConsumptionType::tracksCost()} is true.
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    #[Assert\PositiveOrZero]
    #[Assert\Regex(pattern: '/^\d{1,10}(\.\d{1,2})?$/', message: 'Introduce un importe válido (usa el punto, máx. 2 decimales).')]
    private ?string $cost = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
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
     * Validates that a cost is present only when the consumption type tracks cost.
     */
    #[Assert\Callback]
    public function validateCost(\Symfony\Component\Validator\Context\ExecutionContextInterface $context): void
    {
        // isset() guards the non-nullable typed $type: when the form is submitted without a
        // type, it stays uninitialized and a direct access would be a fatal error here.
        if (null !== $this->cost && isset($this->type) && !$this->type->tracksCost()) {
            $context->buildViolation('This consumption type does not record a cost.')
                ->atPath('cost')
                ->addViolation();
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): ConsumptionType
    {
        return $this->type;
    }

    public function setType(ConsumptionType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getPeriodYear(): int
    {
        return $this->periodYear;
    }

    public function setPeriodYear(int $periodYear): static
    {
        $this->periodYear = $periodYear;

        return $this;
    }

    public function getPeriodMonth(): int
    {
        return $this->periodMonth;
    }

    public function setPeriodMonth(int $periodMonth): static
    {
        $this->periodMonth = $periodMonth;

        return $this;
    }

    public function getQuantity(): string
    {
        return $this->quantity;
    }

    public function setQuantity(string $quantity): static
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getCost(): ?string
    {
        return $this->cost;
    }

    public function setCost(?string $cost): static
    {
        $this->cost = $cost;

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
