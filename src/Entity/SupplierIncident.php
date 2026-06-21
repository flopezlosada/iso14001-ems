<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SupplierIncidentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * An incident detected during the commercial relationship with a {@see Supplier} (PC.05 §5.6):
 * a delivery-time, quality or requirement-compliance problem. Severe or repeated incidents are
 * candidates for opening a non-conformity (a human decision, not automatic).
 */
#[ORM\Entity(repositoryClass: SupplierIncidentRepository::class)]
#[ORM\Table(name: 'supplier_incident')]
#[ORM\HasLifecycleCallbacks]
class SupplierIncident
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Supplier::class, inversedBy: 'incidents')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Supplier $supplier;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull]
    private \DateTimeImmutable $occurredOn;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    private string $description;

    /**
     * Resolution / actions taken ("Resolución"); null while pending.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $resolution = null;

    /**
     * Whether the incident is severe or repeated, i.e. a candidate for opening a non-conformity.
     */
    #[ORM\Column]
    private bool $severe = false;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->occurredOn = new \DateTimeImmutable();
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

    public function getSupplier(): Supplier
    {
        return $this->supplier;
    }

    public function setSupplier(Supplier $supplier): static
    {
        $this->supplier = $supplier;

        return $this;
    }

    public function getOccurredOn(): \DateTimeImmutable
    {
        return $this->occurredOn;
    }

    public function setOccurredOn(\DateTimeImmutable $occurredOn): static
    {
        $this->occurredOn = $occurredOn;

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

    public function getResolution(): ?string
    {
        return $this->resolution;
    }

    public function setResolution(?string $resolution): static
    {
        $this->resolution = $resolution;

        return $this;
    }

    public function isSevere(): bool
    {
        return $this->severe;
    }

    public function setSevere(bool $severe): static
    {
        $this->severe = $severe;

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
