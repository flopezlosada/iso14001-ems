<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\OperationalControlSection;
use App\Repository\OperationalControlItemRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * One configurable item of the monthly operational-control checklist (PG-08.01 / RG-08.01.01): the
 * catalogue that defines WHAT is inspected. Grouped by {@see OperationalControlSection} and ordered
 * by {@see $position}. Items live as data (not as columns) so the checklist can change without a
 * migration and, later, be edited from the UI.
 */
#[ORM\Entity(repositoryClass: OperationalControlItemRepository::class)]
#[ORM\Table(name: 'operational_control_item')]
#[ORM\HasLifecycleCallbacks]
class OperationalControlItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20, enumType: OperationalControlSection::class)]
    private OperationalControlSection $section;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $label;

    /**
     * Order of the item within its section (ascending).
     */
    #[ORM\Column]
    private int $position = 0;

    #[ORM\Column]
    private bool $active = true;

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

    public function getSection(): OperationalControlSection
    {
        return $this->section;
    }

    public function setSection(OperationalControlSection $section): static
    {
        $this->section = $section;

        return $this;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
