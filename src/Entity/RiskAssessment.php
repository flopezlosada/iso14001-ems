<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\RiskCategory;
use App\Enum\RiskLevel;
use App\Repository\RiskAssessmentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * One school year's valuation of a {@see RiskOpportunity} (PC.03.0 §5.2). Holds the two scoring
 * factors and the action plan for that exercise; revised at least yearly with Direction's approval.
 *
 * The {@see $score} and {@see $category} are computed by {@see \App\Service\RiskScoreCalculator}
 * on save (single source of the rule) and are never edited directly.
 */
#[ORM\Entity(repositoryClass: RiskAssessmentRepository::class)]
#[ORM\Table(name: 'risk_assessment')]
#[ORM\UniqueConstraint(name: 'uniq_risk_assessment_exercise', columns: ['risk_opportunity_id', 'exercise'])]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['riskOpportunity', 'exercise'], message: 'Ya existe una valoración para este curso.')]
class RiskAssessment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: RiskOpportunity::class, inversedBy: 'assessments')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private RiskOpportunity $riskOpportunity;

    /**
     * School year this valuation belongs to, e.g. "2025-2026" (the F.08.0 is kept per course).
     */
    #[ORM\Column(length: 9)]
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^\d{4}-\d{4}$/', message: 'El curso debe tener el formato AAAA-AAAA, p. ej. 2025-2026.')]
    private string $exercise;

    /**
     * First factor (1-3): probability for risks, potentiality for opportunities (PC.03.0 §5.2).
     */
    #[ORM\Column(type: Types::SMALLINT, enumType: RiskLevel::class)]
    #[Assert\NotNull]
    private RiskLevel $probability;

    #[ORM\Column(type: Types::SMALLINT, enumType: RiskLevel::class)]
    #[Assert\NotNull]
    private RiskLevel $impact;

    /**
     * Computed score (probability × impact ∈ {1,2,3,4,6,9}). Maintained by the calculator on save.
     */
    #[ORM\Column(type: Types::SMALLINT)]
    private int $score = 0;

    /**
     * Computed category (trivial/moderate/critical). Maintained by the calculator on save; null
     * only before the first calculation.
     */
    #[ORM\Column(length: 20, nullable: true, enumType: RiskCategory::class)]
    private ?RiskCategory $category = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $justification = null;

    /**
     * Revision number within the exercise (Rev. 01, Rev. 02… approved by Direction).
     */
    #[ORM\Column(type: Types::SMALLINT)]
    #[Assert\Positive]
    private int $revisionNumber = 1;

    /**
     * The person (Direction) who approved this revision. Null while the revision is a draft.
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $approvedBy = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $approvedAt = null;

    /**
     * @var Collection<int, RiskAction>
     */
    #[ORM\OneToMany(targetEntity: RiskAction::class, mappedBy: 'assessment', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $actions;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->actions = new ArrayCollection();
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
     * Creates a fresh copy of this valuation for another school year, to seed the new course from the
     * previous one as an editable draft. It carries over the two scoring factors, the justification
     * and the action plan, but starts as an unapproved Rev. 01 (Dirección must sign it off again) and
     * its actions reset their efficacy review (each course re-evaluates them). The copy stays attached
     * to the same {@see RiskOpportunity}; its score/category are left for {@see RiskScoreCalculator}
     * to recompute on save (single source of the rule).
     *
     * @param string $exercise the school year the copy belongs to, in "YYYY-YYYY" format
     *
     * @return self a new, unpersisted draft valuation for the given exercise
     */
    public function copyForExercise(string $exercise): self
    {
        $copy = (new self())
            ->setRiskOpportunity($this->riskOpportunity)
            ->setExercise($exercise)
            ->setProbability($this->probability)
            ->setImpact($this->impact)
            ->setJustification($this->justification);

        foreach ($this->actions as $action) {
            $copy->addAction(
                (new RiskAction())
                    ->setDescription($action->getDescription())
                    ->setResponsible($action->getResponsible())
                    ->setDeadline($action->getDeadline()),
            );
        }

        return $copy;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRiskOpportunity(): RiskOpportunity
    {
        return $this->riskOpportunity;
    }

    public function setRiskOpportunity(RiskOpportunity $riskOpportunity): static
    {
        $this->riskOpportunity = $riskOpportunity;

        return $this;
    }

    public function getExercise(): string
    {
        return $this->exercise;
    }

    public function setExercise(string $exercise): static
    {
        $this->exercise = $exercise;

        return $this;
    }

    public function getProbability(): RiskLevel
    {
        return $this->probability;
    }

    public function setProbability(RiskLevel $probability): static
    {
        $this->probability = $probability;

        return $this;
    }

    public function getImpact(): RiskLevel
    {
        return $this->impact;
    }

    public function setImpact(RiskLevel $impact): static
    {
        $this->impact = $impact;

        return $this;
    }

    public function getScore(): int
    {
        return $this->score;
    }

    public function setScore(int $score): static
    {
        $this->score = $score;

        return $this;
    }

    public function getCategory(): ?RiskCategory
    {
        return $this->category;
    }

    public function setCategory(?RiskCategory $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getJustification(): ?string
    {
        return $this->justification;
    }

    public function setJustification(?string $justification): static
    {
        $this->justification = $justification;

        return $this;
    }

    public function getRevisionNumber(): int
    {
        return $this->revisionNumber;
    }

    public function setRevisionNumber(int $revisionNumber): static
    {
        $this->revisionNumber = $revisionNumber;

        return $this;
    }

    public function getApprovedBy(): ?User
    {
        return $this->approvedBy;
    }

    public function setApprovedBy(?User $approvedBy): static
    {
        $this->approvedBy = $approvedBy;

        return $this;
    }

    public function getApprovedAt(): ?\DateTimeImmutable
    {
        return $this->approvedAt;
    }

    public function setApprovedAt(?\DateTimeImmutable $approvedAt): static
    {
        $this->approvedAt = $approvedAt;

        return $this;
    }

    /**
     * @return Collection<int, RiskAction> the planned actions for this assessment
     */
    public function getActions(): Collection
    {
        return $this->actions;
    }

    public function addAction(RiskAction $action): static
    {
        if (!$this->actions->contains($action)) {
            $this->actions->add($action);
            $action->setAssessment($this);
        }

        return $this;
    }

    public function removeAction(RiskAction $action): static
    {
        $this->actions->removeElement($action);

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
