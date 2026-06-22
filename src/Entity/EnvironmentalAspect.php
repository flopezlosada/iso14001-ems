<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\AspectType;
use App\Enum\ConsumptionType;
use App\Enum\DirectAspectCategory;
use App\Repository\EnvironmentalAspectRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * An environmental aspect of the centre (PG-06.01 / register RG-06.01.01): a catalogue entry
 * (e.g. "Electricidad", "Residuos de tóner") that is re-evaluated yearly. This module covers
 * direct aspects; {@see $category} classifies them (consumption/emission/waste/discharge).
 */
#[ORM\Entity(repositoryClass: EnvironmentalAspectRepository::class)]
#[ORM\Table(name: 'environmental_aspect')]
#[ORM\HasLifecycleCallbacks]
class EnvironmentalAspect
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $name;

    #[ORM\Column(length: 20, enumType: AspectType::class)]
    private AspectType $type = AspectType::DIRECT;

    /**
     * Category for direct aspects (null for other types).
     */
    #[ORM\Column(length: 20, nullable: true, enumType: DirectAspectCategory::class)]
    private ?DirectAspectCategory $category = null;

    /**
     * Unit of measurement of the aspect's quantity (e.g. "kWh", "m³", "Kg"); optional.
     */
    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\Length(max: 50)]
    private ?string $unit = null;

    /**
     * Utility whose monthly readings feed this aspect's auto-intensity: its consumption is compared
     * interannually to suggest the intensity score (PG-06.01). Only meaningful for direct
     * CONSUMPTION aspects; null means the intensity is entered manually (no linked data source).
     */
    #[ORM\Column(length: 20, nullable: true, enumType: ConsumptionType::class)]
    private ?ConsumptionType $linkedConsumptionType = null;

    /**
     * Associated environmental impact ("Impacto asociado"), e.g. "Agotamiento recurso natural".
     */
    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $associatedImpact = null;

    #[ORM\Column]
    private bool $active = true;

    /**
     * @var Collection<int, AspectEvaluation>
     */
    #[ORM\OneToMany(targetEntity: AspectEvaluation::class, mappedBy: 'aspect', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['year' => 'DESC'])]
    private Collection $evaluations;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
        $this->evaluations = new ArrayCollection();
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * Most recent evaluation by year (the current standing), or null if never evaluated.
     */
    public function getLatestEvaluation(): ?AspectEvaluation
    {
        return $this->evaluations->first() ?: null;
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

    public function getType(): AspectType
    {
        return $this->type;
    }

    public function setType(AspectType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getCategory(): ?DirectAspectCategory
    {
        return $this->category;
    }

    public function setCategory(?DirectAspectCategory $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getUnit(): ?string
    {
        return $this->unit;
    }

    public function setUnit(?string $unit): static
    {
        $this->unit = $unit;

        return $this;
    }

    /**
     * The utility linked as the data source for this aspect's auto-intensity, or null when none.
     */
    public function getLinkedConsumptionType(): ?ConsumptionType
    {
        return $this->linkedConsumptionType;
    }

    /**
     * Links (or unlinks, with null) the utility whose readings drive this aspect's auto-intensity.
     */
    public function setLinkedConsumptionType(?ConsumptionType $linkedConsumptionType): static
    {
        $this->linkedConsumptionType = $linkedConsumptionType;

        return $this;
    }

    public function getAssociatedImpact(): ?string
    {
        return $this->associatedImpact;
    }

    public function setAssociatedImpact(?string $associatedImpact): static
    {
        $this->associatedImpact = $associatedImpact;

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
     * @return Collection<int, AspectEvaluation> the yearly evaluations, most recent first
     */
    public function getEvaluations(): Collection
    {
        return $this->evaluations;
    }

    public function addEvaluation(AspectEvaluation $evaluation): static
    {
        if (!$this->evaluations->contains($evaluation)) {
            $this->evaluations->add($evaluation);
            $evaluation->setAspect($this);
        }

        return $this;
    }

    public function removeEvaluation(AspectEvaluation $evaluation): static
    {
        $this->evaluations->removeElement($evaluation);

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
