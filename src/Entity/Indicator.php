<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\MeasurementPeriodicity;
use App\Enum\SgmaProcess;
use App\Repository\IndicatorRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A performance indicator of the management system (F.09.0). A catalogue entry that is measured
 * periodically; the measurement formula is embedded in {@see $measurementDescription} and the
 * threshold in {@see $referenceValue} (free text, as the register records it).
 */
#[ORM\Entity(repositoryClass: IndicatorRepository::class)]
#[ORM\Table(name: 'indicator')]
#[ORM\HasLifecycleCallbacks]
class Indicator
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $name;

    /**
     * How the indicator is measured ("Descripción medición"); the formula lives here.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $measurementDescription = null;

    /**
     * Threshold / target ("Valor de referencia"), free text (e.g. "5%", "NINGUNA", "0").
     */
    #[ORM\Column(length: 120, nullable: true)]
    #[Assert\Length(max: 120)]
    private ?string $referenceValue = null;

    #[ORM\Column(length: 30, enumType: SgmaProcess::class)]
    private SgmaProcess $process;

    #[ORM\Column(length: 20, enumType: MeasurementPeriodicity::class)]
    private MeasurementPeriodicity $periodicity = MeasurementPeriodicity::MONTHLY;

    #[ORM\Column]
    private bool $active = true;

    /**
     * @var Collection<int, IndicatorMeasurement>
     */
    #[ORM\OneToMany(targetEntity: IndicatorMeasurement::class, mappedBy: 'indicator', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['year' => 'DESC', 'month' => 'DESC'])]
    private Collection $measurements;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
        $this->measurements = new ArrayCollection();
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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getMeasurementDescription(): ?string
    {
        return $this->measurementDescription;
    }

    public function setMeasurementDescription(?string $measurementDescription): static
    {
        $this->measurementDescription = $measurementDescription;

        return $this;
    }

    public function getReferenceValue(): ?string
    {
        return $this->referenceValue;
    }

    public function setReferenceValue(?string $referenceValue): static
    {
        $this->referenceValue = $referenceValue;

        return $this;
    }

    public function getProcess(): SgmaProcess
    {
        return $this->process;
    }

    public function setProcess(SgmaProcess $process): static
    {
        $this->process = $process;

        return $this;
    }

    public function getPeriodicity(): MeasurementPeriodicity
    {
        return $this->periodicity;
    }

    public function setPeriodicity(MeasurementPeriodicity $periodicity): static
    {
        $this->periodicity = $periodicity;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    /**
     * @return Collection<int, IndicatorMeasurement> the measurements, most recent first
     */
    public function getMeasurements(): Collection
    {
        return $this->measurements;
    }

    public function addMeasurement(IndicatorMeasurement $measurement): static
    {
        if (!$this->measurements->contains($measurement)) {
            $this->measurements->add($measurement);
            $measurement->setIndicator($this);
        }

        return $this;
    }

    public function removeMeasurement(IndicatorMeasurement $measurement): static
    {
        $this->measurements->removeElement($measurement);

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
