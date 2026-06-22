<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\WasteRecordRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A single waste pick-up entry of the chronological waste register (event-driven: one record per
 * removal). Captures the data a waste log needs: LER code, description, amount, pick-up date and
 * authorised manager.
 */
#[ORM\Entity(repositoryClass: WasteRecordRepository::class)]
#[ORM\Table(name: 'waste_record')]
#[ORM\Index(name: 'idx_waste_pickup_date', columns: ['pickup_date'])]
#[ORM\HasLifecycleCallbacks]
class WasteRecord
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * European Waste Catalogue (LER/EWC) code, e.g. "200121". Nullable because the real historical
     * register holds entries whose code was lost (mangled by Excel into a date serial) and cannot
     * be reconstructed automatically; the original illegible value is preserved in {@see $notes}.
     */
    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Length(max: 20)]
    #[Assert\Regex(pattern: '/^\d{2}\s?\d{2}\s?\d{2}\*?$/', message: 'El código LER debe tener 6 dígitos (p. ej. 200121 o 20 01 21), con * si es peligroso.')]
    private ?string $lerCode = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $description;

    /**
     * Amount removed, in kilograms. Doctrine decimal mapped as string to keep precision. Nullable
     * because some real entries record the amount in non-weight units (e.g. "43 bolsones"); the
     * kilograms are then unknown and the original wording is kept in {@see $notes}.
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 3, nullable: true)]
    #[Assert\PositiveOrZero]
    #[Assert\Regex(pattern: '/^\d{1,9}(\.\d{1,3})?$/', message: 'Introduce una cantidad válida (usa el punto, máx. 3 decimales).')]
    private ?string $quantityKg = null;

    /**
     * Pick-up date. Nullable because some real entries date the removal in free text (a month or a
     * range); the original wording is then preserved in {@see $notes}.
     */
    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $pickupDate = null;

    /**
     * Authorised waste manager (gestor) that collected the waste. Nullable because most real entries
     * (internal composting, municipal clean point) have no formal manager.
     */
    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $manager = null;

    #[ORM\Column]
    private bool $hazardous = false;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    /**
     * Deterministic hash of the source row when this record was imported from the historical
     * register; null for records created manually. Lets the importer upsert idempotently, since
     * the chronological register has no reliable natural key (compost entries share description and
     * carry a free-text date).
     */
    #[ORM\Column(length: 64, nullable: true, unique: true)]
    private ?string $sourceHash = null;

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

    public function getLerCode(): ?string
    {
        return $this->lerCode;
    }

    public function setLerCode(?string $lerCode): static
    {
        $this->lerCode = $lerCode;

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

    public function getQuantityKg(): ?string
    {
        return $this->quantityKg;
    }

    public function setQuantityKg(?string $quantityKg): static
    {
        $this->quantityKg = $quantityKg;

        return $this;
    }

    public function getPickupDate(): ?\DateTimeImmutable
    {
        return $this->pickupDate;
    }

    public function setPickupDate(?\DateTimeImmutable $pickupDate): static
    {
        $this->pickupDate = $pickupDate;

        return $this;
    }

    public function getManager(): ?string
    {
        return $this->manager;
    }

    public function setManager(?string $manager): static
    {
        $this->manager = $manager;

        return $this;
    }

    public function isHazardous(): bool
    {
        return $this->hazardous;
    }

    public function setHazardous(bool $hazardous): static
    {
        $this->hazardous = $hazardous;

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

    public function getSourceHash(): ?string
    {
        return $this->sourceHash;
    }

    public function setSourceHash(?string $sourceHash): static
    {
        $this->sourceHash = $sourceHash;

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
