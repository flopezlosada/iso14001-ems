<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ProcessAreaRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A process or functional area of the centre ("Proceso/ÁREA" column of F.08.0): e.g. Formación,
 * Secretaría, Dirección, Mantenimiento.
 *
 * Modelled as a UI-configurable catalogue rather than free text: the real records write the same
 * area in many inconsistent ways, so a controlled list keeps grouping and filtering reliable.
 */
#[ORM\Entity(repositoryClass: ProcessAreaRepository::class)]
#[ORM\Table(name: 'process_area')]
#[ORM\UniqueConstraint(name: 'uniq_process_area_name', columns: ['name'])]
class ProcessArea
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private string $name;

    /**
     * Whether the area can be assigned to new items. Areas are never deleted (they may be
     * referenced by historical risks kept for traceability), only deactivated.
     */
    #[ORM\Column]
    private bool $active = true;

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
     * String representation, used by form choice widgets and listings.
     *
     * @return string the area name
     */
    public function __toString(): string
    {
        return $this->name;
    }
}
