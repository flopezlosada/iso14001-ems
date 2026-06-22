<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\OperationalControlCheckRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * One monthly operational-control inspection (PG-08.01 / RG-08.01.01): the header of a checklist
 * filled in once per calendar month, with one {@see OperationalControlAnswer} per checked item.
 * There is at most one inspection per (year, month).
 */
#[ORM\Entity(repositoryClass: OperationalControlCheckRepository::class)]
#[ORM\Table(name: 'operational_control_check')]
#[ORM\UniqueConstraint(name: 'uniq_opcontrol_period', columns: ['period_year', 'period_month'])]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['periodYear', 'periodMonth'], message: 'Ya existe un control operacional para ese mes.')]
class OperationalControlCheck
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'period_year')]
    #[Assert\Range(min: 2000, max: 2100)]
    private int $periodYear;

    #[ORM\Column(name: 'period_month')]
    #[Assert\Range(min: 1, max: 12)]
    private int $periodMonth;

    /**
     * Name of the person who carried out the inspection ("Realizado por" on the form).
     */
    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $performedBy;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $observations = null;

    /**
     * @var Collection<int, OperationalControlAnswer>
     */
    #[ORM\OneToMany(targetEntity: OperationalControlAnswer::class, mappedBy: 'check', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $answers;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
        $this->answers = new ArrayCollection();
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * Number of items answered "No conforme" in this inspection, for the at-a-glance summary.
     *
     * @return int the count of non-conform answers
     */
    public function countNonConform(): int
    {
        return $this->answers
            ->filter(static fn (OperationalControlAnswer $a): bool => $a->isNonConform())
            ->count();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPeriodYear(): int
    {
        return $this->periodYear;
    }

    public function setPeriodYear(int $periodYear): static
    {
        $this->periodYear = $periodYear;

        return $this;
    }

    public function getPeriodMonth(): int
    {
        return $this->periodMonth;
    }

    public function setPeriodMonth(int $periodMonth): static
    {
        $this->periodMonth = $periodMonth;

        return $this;
    }

    public function getPerformedBy(): string
    {
        return $this->performedBy;
    }

    public function setPerformedBy(string $performedBy): static
    {
        $this->performedBy = $performedBy;

        return $this;
    }

    public function getObservations(): ?string
    {
        return $this->observations;
    }

    public function setObservations(?string $observations): static
    {
        $this->observations = $observations;

        return $this;
    }

    /**
     * @return Collection<int, OperationalControlAnswer> the answers, one per checked item
     */
    public function getAnswers(): Collection
    {
        return $this->answers;
    }

    public function addAnswer(OperationalControlAnswer $answer): static
    {
        if (!$this->answers->contains($answer)) {
            $this->answers->add($answer);
            $answer->setCheck($this);
        }

        return $this;
    }

    public function removeAnswer(OperationalControlAnswer $answer): static
    {
        $this->answers->removeElement($answer);

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
