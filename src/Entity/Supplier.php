<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SupplierRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * An external supplier of products or services (PC.05 / register F.12.0). Suppliers are
 * re-evaluated yearly; each {@see SupplierEvaluation} captures one year's standing, so the table
 * keeps the "Estado 2024/2025/2026" history the centre maintains.
 */
#[ORM\Entity(repositoryClass: SupplierRepository::class)]
#[ORM\Table(name: 'supplier')]
#[ORM\HasLifecycleCallbacks]
class Supplier
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
     * Product and/or service provided ("Servicio y/o producto").
     */
    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $productOrService;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    /**
     * Yearly evaluations of this supplier (the annual re-evaluations of PC.05 §5.6).
     *
     * @var Collection<int, SupplierEvaluation>
     */
    #[ORM\OneToMany(targetEntity: SupplierEvaluation::class, mappedBy: 'supplier', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['year' => 'DESC'])]
    private Collection $evaluations;

    /**
     * Incidents detected during the commercial relationship (PC.05 §5.6).
     *
     * @var Collection<int, SupplierIncident>
     */
    #[ORM\OneToMany(targetEntity: SupplierIncident::class, mappedBy: 'supplier', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['occurredOn' => 'DESC'])]
    private Collection $incidents;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
        $this->evaluations = new ArrayCollection();
        $this->incidents = new ArrayCollection();
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
    public function getLatestEvaluation(): ?SupplierEvaluation
    {
        // The collection is ordered by year DESC, so the first element is the latest.
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

    public function getProductOrService(): string
    {
        return $this->productOrService;
    }

    public function setProductOrService(string $productOrService): static
    {
        $this->productOrService = $productOrService;

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

    /**
     * @return Collection<int, SupplierEvaluation> the yearly evaluations, most recent first
     */
    public function getEvaluations(): Collection
    {
        return $this->evaluations;
    }

    public function addEvaluation(SupplierEvaluation $evaluation): static
    {
        if (!$this->evaluations->contains($evaluation)) {
            $this->evaluations->add($evaluation);
            $evaluation->setSupplier($this);
        }

        return $this;
    }

    public function removeEvaluation(SupplierEvaluation $evaluation): static
    {
        $this->evaluations->removeElement($evaluation);

        return $this;
    }

    /**
     * @return Collection<int, SupplierIncident> the incidents, most recent first
     */
    public function getIncidents(): Collection
    {
        return $this->incidents;
    }

    public function addIncident(SupplierIncident $incident): static
    {
        if (!$this->incidents->contains($incident)) {
            $this->incidents->add($incident);
            $incident->setSupplier($this);
        }

        return $this;
    }

    public function removeIncident(SupplierIncident $incident): static
    {
        $this->incidents->removeElement($incident);

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
