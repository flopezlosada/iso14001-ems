<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A person who uses the system. Holds one or more {@see Role}s (a person can be responsible for
 * several areas, and an area can have several co-responsibles).
 *
 * Authentication (password / login) is intentionally not modelled yet; it will be added with
 * Symfony Security. The role collection is exposed as {@see getAssignedRoles()} on purpose:
 * the name getRoles() is reserved for Symfony's UserInterface contract (which returns string[]),
 * to avoid a signature clash once this entity implements it.
 */
#[ORM\Entity]
#[ORM\Table(name: 'app_user')]
class User
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    private string $fullName;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Email]
    private string $email;

    #[ORM\Column]
    private bool $active = true;

    /** @var Collection<int, Role> */
    #[ORM\ManyToMany(targetEntity: Role::class, inversedBy: 'users')]
    #[ORM\JoinTable(name: 'user_role')]
    private Collection $assignedRoles;

    public function __construct()
    {
        $this->assignedRoles = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFullName(): string
    {
        return $this->fullName;
    }

    public function setFullName(string $fullName): static
    {
        $this->fullName = $fullName;

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

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
     * The responsibilities held by this user. Named to avoid clashing with
     * {@see \Symfony\Component\Security\Core\User\UserInterface::getRoles()}.
     *
     * @return Collection<int, Role>
     */
    public function getAssignedRoles(): Collection
    {
        return $this->assignedRoles;
    }

    public function addRole(Role $role): static
    {
        if (!$this->assignedRoles->contains($role)) {
            $this->assignedRoles->add($role);
        }

        return $this;
    }

    public function removeRole(Role $role): static
    {
        $this->assignedRoles->removeElement($role);

        return $this;
    }
}
