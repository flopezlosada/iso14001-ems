<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\RiskOpportunityType;
use App\Repository\RiskOpportunityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A risk or opportunity identified for the SGMA (PC.03.0 / F.08.0). The item itself is stable over
 * time; its valuation and action plan are revised each school year through {@see RiskAssessment}.
 */
#[ORM\Entity(repositoryClass: RiskOpportunityRepository::class)]
#[ORM\Table(name: 'risk_opportunity')]
#[ORM\HasLifecycleCallbacks]
class RiskOpportunity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20, enumType: RiskOpportunityType::class)]
    private RiskOpportunityType $type;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    private string $description;

    #[ORM\ManyToOne(targetEntity: ProcessArea::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private ProcessArea $processArea;

    /**
     * @var Collection<int, RiskAssessment>
     */
    #[ORM\OneToMany(targetEntity: RiskAssessment::class, mappedBy: 'riskOpportunity', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['exercise' => 'DESC'])]
    private Collection $assessments;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->assessments = new ArrayCollection();
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

    public function getType(): RiskOpportunityType
    {
        return $this->type;
    }

    public function setType(RiskOpportunityType $type): static
    {
        $this->type = $type;

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

    public function getProcessArea(): ProcessArea
    {
        return $this->processArea;
    }

    public function setProcessArea(ProcessArea $processArea): static
    {
        $this->processArea = $processArea;

        return $this;
    }

    /**
     * @return Collection<int, RiskAssessment> the yearly assessments, most recent first
     */
    public function getAssessments(): Collection
    {
        return $this->assessments;
    }

    public function addAssessment(RiskAssessment $assessment): static
    {
        if (!$this->assessments->contains($assessment)) {
            $this->assessments->add($assessment);
            $assessment->setRiskOpportunity($this);
        }

        return $this;
    }

    public function removeAssessment(RiskAssessment $assessment): static
    {
        $this->assessments->removeElement($assessment);

        return $this;
    }

    /**
     * The most recent valuation (assessments are ordered by exercise descending), or null when the
     * item has not been valued yet.
     *
     * @return RiskAssessment|null the latest assessment
     */
    public function getLatestAssessment(): ?RiskAssessment
    {
        return $this->assessments->first() ?: null;
    }

    /**
     * The valuation for a given exercise, or null if the item has not been valued that school year.
     * Iterates the already-loaded collection, so it triggers no query when the assessments are
     * eager-fetched (as in the module listing).
     *
     * @param string $exercise the school year, in "YYYY-YYYY" format
     *
     * @return RiskAssessment|null the valuation of that exercise, or null when there is none
     */
    public function assessmentFor(string $exercise): ?RiskAssessment
    {
        foreach ($this->assessments as $assessment) {
            if ($assessment->getExercise() === $exercise) {
                return $assessment;
            }
        }

        return null;
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
