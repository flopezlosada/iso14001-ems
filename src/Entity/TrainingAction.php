<?php
declare(strict_types=1);

namespace App\Entity;

use App\Enum\TrainingType;
use App\Repository\TrainingActionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A single training action of the annual training plan (form F.03.0). Training actions are
 * grouped by {@see $planYear} so each year's plan can be listed on its own page.
 *
 * NOTE: the planned/actual execution dates are modelled as calendar dates ({@see \DateTimeImmutable}).
 * This is a decision already taken internally (do NOT reopen the string-vs-date debate), still
 * subject to the centre signing off at the cutover: if a strict date does not fit their reality,
 * they will say so. The real F.03.0 sheet often holds free text there ("octubre 2023", "23 al
 * 27/10/23", "a la semana de su incorporación"); rather than weaken the model to a string, that text
 * is normalized in the real-data ETL by {@see \App\Service\Import\TrainingDateNormalizer} (month ->
 * first day, range -> start day).
 *
 * When the ETL cannot normalize the planned date or the delivery type, the row is no longer dropped
 * to quarantine: it is imported with the un-normalizable field left null, {@see $needsReview} set and
 * the original raw text recorded in {@see $reviewNote}, so the centre fixes it in the UI and clears
 * the flag — far less friction than handing them a CSV to edit. This is why {@see $plannedDate} and
 * {@see $type} are nullable: null means "pending review", not a valid empty value.
 */
#[ORM\Entity(repositoryClass: TrainingActionRepository::class)]
#[ORM\Table(name: 'training_action')]
#[ORM\HasLifecycleCallbacks]
class TrainingAction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'plan_year')]
    #[Assert\Range(min: 2000, max: 2100)]
    private int $planYear;

    /**
     * Course description ("Descripción del curso").
     */
    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $description;

    /**
     * Whether the action is delivered in-house or externally ("EXT/INT"). Null while pending review
     * (e.g. the source said "int/ext"); see {@see $needsReview}.
     */
    #[ORM\Column(enumType: TrainingType::class, nullable: true)]
    private ?TrainingType $type = null;

    /**
     * Roles or staff the action is aimed at ("Profesionales/puestos a los que va dirigido").
     */
    #[ORM\Column(name: 'target_audience', length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $targetAudience;

    /**
     * Learning objectives ("Objetivos").
     */
    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    private string $objectives;

    /**
     * Planned execution date ("Fecha prevista de ejecución"). Provisional calendar date; see the
     * class-level note. Null while pending review (the source date could not be normalized).
     */
    #[ORM\Column(name: 'planned_date', type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $plannedDate = null;

    /**
     * Training methodology ("Metodología de formación").
     */
    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    private string $methodology;

    /**
     * Actual execution date ("Fecha real de ejecución"), unknown until the action takes place.
     * Provisional calendar date; see the class-level note.
     */
    #[ORM\Column(name: 'actual_date', type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $actualDate = null;

    /**
     * Evaluation of the effectiveness of the action ("Evaluación de la eficacia"), recorded after
     * it has been delivered.
     */
    #[ORM\Column(name: 'efficacy_evaluation', type: Types::TEXT, nullable: true)]
    private ?string $efficacyEvaluation = null;

    /**
     * Whether the action carries data the centre still has to verify by hand. Set by the real-data
     * ETL when a source value could not be normalized (see {@see $reviewNote}); cleared from the UI
     * once a human has fixed the row.
     */
    #[ORM\Column(name: 'needs_review', options: ['default' => false])]
    private bool $needsReview = false;

    /**
     * Human-readable explanation of why {@see $needsReview} is set, including the original raw text
     * that could not be normalized (e.g. 'Fecha prevista original: "sin det".'). Null when nothing
     * needs review.
     */
    #[ORM\Column(name: 'review_note', type: Types::TEXT, nullable: true)]
    private ?string $reviewNote = null;

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

    public function getPlanYear(): int
    {
        return $this->planYear;
    }

    public function setPlanYear(int $planYear): static
    {
        $this->planYear = $planYear;

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

    public function getType(): ?TrainingType
    {
        return $this->type;
    }

    public function setType(?TrainingType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getTargetAudience(): string
    {
        return $this->targetAudience;
    }

    public function setTargetAudience(string $targetAudience): static
    {
        $this->targetAudience = $targetAudience;

        return $this;
    }

    public function getObjectives(): string
    {
        return $this->objectives;
    }

    public function setObjectives(string $objectives): static
    {
        $this->objectives = $objectives;

        return $this;
    }

    public function getPlannedDate(): ?\DateTimeImmutable
    {
        return $this->plannedDate;
    }

    public function setPlannedDate(?\DateTimeImmutable $plannedDate): static
    {
        $this->plannedDate = $plannedDate;

        return $this;
    }

    public function getMethodology(): string
    {
        return $this->methodology;
    }

    public function setMethodology(string $methodology): static
    {
        $this->methodology = $methodology;

        return $this;
    }

    public function getActualDate(): ?\DateTimeImmutable
    {
        return $this->actualDate;
    }

    public function setActualDate(?\DateTimeImmutable $actualDate): static
    {
        $this->actualDate = $actualDate;

        return $this;
    }

    public function getEfficacyEvaluation(): ?string
    {
        return $this->efficacyEvaluation;
    }

    public function setEfficacyEvaluation(?string $efficacyEvaluation): static
    {
        $this->efficacyEvaluation = $efficacyEvaluation;

        return $this;
    }

    public function isNeedsReview(): bool
    {
        return $this->needsReview;
    }

    public function setNeedsReview(bool $needsReview): static
    {
        $this->needsReview = $needsReview;

        return $this;
    }

    public function getReviewNote(): ?string
    {
        return $this->reviewNote;
    }

    public function setReviewNote(?string $reviewNote): static
    {
        $this->reviewNote = $reviewNote;

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
