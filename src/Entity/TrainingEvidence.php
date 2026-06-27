<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TrainingEvidenceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A single piece of evidence that a person actually received environmental training (register
 * "Registro de evidencias de formación ambiental", ISO 14001:2015 §7.2/§7.3, obligation #18 of the
 * centre's document relation). One row per person and training received: who, which training, when
 * and whether they completed the comprehension questionnaire.
 *
 * Unlike the annual training plan ({@see TrainingAction}, form F.03.0) which lists the planned
 * actions, this is a flat evidence log of what was actually delivered, listed newest first.
 *
 * An evidence may relate to a planned action of the training plan ({@see $trainingAction}) when the
 * training comes from the plan; the link is optional (a new joiner or an ad-hoc session has no
 * planned action) and severed (set to NULL) rather than cascaded if that action is deleted, so the
 * evidence record is never lost.
 *
 * Holds personal data ({@see $personName}): it is loaded directly in production, never seeded from
 * git fixtures with real names.
 */
#[ORM\Entity(repositoryClass: TrainingEvidenceRepository::class)]
#[ORM\Table(name: 'training_evidence')]
#[ORM\Index(name: 'idx_training_evidence_date', columns: ['training_date'])]
#[ORM\HasLifecycleCallbacks]
class TrainingEvidence
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Name of the person who received the training ("nombre"). Personal data.
     */
    #[ORM\Column(name: 'person_name', length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $personName;

    /**
     * Which training was received ("tipo de formación"), e.g. "Sensibilización ambiental ISO 14001".
     */
    #[ORM\Column(name: 'training_description', length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $trainingDescription;

    /**
     * The date the training was received ("fecha formación").
     */
    #[ORM\Column(name: 'training_date', type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull]
    private \DateTimeImmutable $trainingDate;

    /**
     * Whether the person completed the comprehension questionnaire ("cuestionario").
     */
    #[ORM\Column(name: 'questionnaire_completed')]
    private bool $questionnaireCompleted = false;

    /**
     * The planned training action this evidence corresponds to, when the training comes from the
     * annual plan. Optional: ad-hoc or new-joiner training has no planned action. Severed to NULL
     * (not cascaded) when the action row is deleted, so the evidence log is preserved.
     */
    #[ORM\ManyToOne(targetEntity: TrainingAction::class)]
    #[ORM\JoinColumn(name: 'training_action_id', nullable: true, onDelete: 'SET NULL')]
    private ?TrainingAction $trainingAction = null;

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

    public function getPersonName(): string
    {
        return $this->personName;
    }

    public function setPersonName(string $personName): static
    {
        $this->personName = $personName;

        return $this;
    }

    public function getTrainingDescription(): string
    {
        return $this->trainingDescription;
    }

    public function setTrainingDescription(string $trainingDescription): static
    {
        $this->trainingDescription = $trainingDescription;

        return $this;
    }

    public function getTrainingDate(): \DateTimeImmutable
    {
        return $this->trainingDate;
    }

    public function setTrainingDate(\DateTimeImmutable $trainingDate): static
    {
        $this->trainingDate = $trainingDate;

        return $this;
    }

    public function isQuestionnaireCompleted(): bool
    {
        return $this->questionnaireCompleted;
    }

    public function setQuestionnaireCompleted(bool $questionnaireCompleted): static
    {
        $this->questionnaireCompleted = $questionnaireCompleted;

        return $this;
    }

    public function getTrainingAction(): ?TrainingAction
    {
        return $this->trainingAction;
    }

    public function setTrainingAction(?TrainingAction $trainingAction): static
    {
        $this->trainingAction = $trainingAction;

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
