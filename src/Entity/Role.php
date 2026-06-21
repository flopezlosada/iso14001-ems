<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A responsibility within the environmental management system (e.g. Direction, EMS Manager,
 * Secretary, Maintenance, Quality).
 *
 * Roles are a configurable catalog, not a fixed enum: several people can share the same
 * responsibility (co-responsibles) and the admin can add people and split tasks. A
 * stable {@see $code} allows programmatic lookups (e.g. approval-by-type rules) independently
 * of the human-facing {@see $name}.
 */
#[ORM\Entity]
class Role
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Stable machine identifier (e.g. "direction", "ems_manager"). Immutable in practice so
     * business rules can reference roles without depending on the display name.
     */
    #[ORM\Column(length: 50, unique: true)]
    #[Assert\NotBlank]
    private string $code;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    private string $name;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    /** @var Collection<int, User> */
    #[ORM\ManyToMany(targetEntity: User::class, mappedBy: 'assignedRoles')]
    private Collection $users;

    public function __construct()
    {
        $this->users = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @return Collection<int, User> the people who currently hold this responsibility
     */
    public function getUsers(): Collection
    {
        return $this->users;
    }
}
