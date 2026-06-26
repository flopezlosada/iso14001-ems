<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\InterestedPartyRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A single interested party of the annual "Identificación y Evaluación de Partes Interesadas"
 * register (form F.04.0 / PPI, ISO 14001 clause 4.2). Each row records who the party is, what it
 * needs/expects from the centre, and any incidents detected during the review.
 *
 * Interested parties are grouped by {@see $reviewYear} so each year's analysis can be listed on its
 * own page, mirroring the sibling annual register F.03.0 ({@see TrainingAction}). The centre keeps a
 * fresh PPI per year (2024, 2025…), so the year is part of the record rather than a global list.
 *
 * This is a plain CRUD record: it has no scoring/calculation engine (unlike environmental aspects
 * or risks). {@see $incidents} is deliberately free text — the real F.04.0 sheet holds values such
 * as "NO" or a short narrative, not a boolean or a closed enum.
 */
#[ORM\Entity(repositoryClass: InterestedPartyRepository::class)]
#[ORM\Table(name: 'interested_party')]
#[ORM\HasLifecycleCallbacks]
class InterestedParty
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'review_year')]
    #[Assert\Range(min: 2000, max: 2100)]
    private int $reviewYear;

    /**
     * The interested party itself ("Partes interesadas"), e.g. "Usuarios/Alumnos", "Proveedores".
     */
    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $name;

    /**
     * What the party needs and expects from the centre ("Necesidades y expectativas").
     */
    #[ORM\Column(name: 'needs_and_expectations', type: Types::TEXT)]
    #[Assert\NotBlank]
    private string $needsAndExpectations;

    /**
     * Incidents detected for this party during the review ("Incidencias"). Free text and optional:
     * the real sheet holds "NO" or a short note, or leaves it blank.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $incidents = null;

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
     * Creates a fresh copy of this interested party for another review year, carrying over its name,
     * needs/expectations and incidents. Used to seed a new year's register from the previous one as
     * an editable draft; the copy is a brand-new entity (no id, own timestamps).
     *
     * @param int $year the review year the copy belongs to
     *
     * @return self a new, unpersisted interested party for the given year
     */
    public function copyForYear(int $year): self
    {
        return (new self())
            ->setReviewYear($year)
            ->setName($this->name)
            ->setNeedsAndExpectations($this->needsAndExpectations)
            ->setIncidents($this->incidents);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReviewYear(): int
    {
        return $this->reviewYear;
    }

    public function setReviewYear(int $reviewYear): static
    {
        $this->reviewYear = $reviewYear;

        return $this;
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

    public function getNeedsAndExpectations(): string
    {
        return $this->needsAndExpectations;
    }

    public function setNeedsAndExpectations(string $needsAndExpectations): static
    {
        $this->needsAndExpectations = $needsAndExpectations;

        return $this;
    }

    public function getIncidents(): ?string
    {
        return $this->incidents;
    }

    public function setIncidents(?string $incidents): static
    {
        $this->incidents = $incidents;

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
