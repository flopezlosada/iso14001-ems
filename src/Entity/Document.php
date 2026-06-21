<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\AlertFrequency;
use App\Enum\Area;
use App\Enum\DocumentType;
use App\Enum\IsoChapter;
use App\Enum\ObligationStatus;
use App\Enum\ObligationUrgency;
use App\Enum\PdcaPhase;
use App\Repository\DocumentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A logical document of the environmental management system (e.g. the policy, an
 * environmental-aspects form, a non-conformity record).
 *
 * The internal auto-generated id is the stable identity. The ISO code (F.XX.Y, RG-..., DO-...)
 * is a mutable attribute, NOT the primary key, because real-world codes are often
 * inconsistent (duplicates, collisions, several codes for the same document). Inherited codes
 * are kept in {@see $legacyCodes} for traceability.
 */
#[ORM\Entity(repositoryClass: DocumentRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Document
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $title;

    #[ORM\Column(enumType: DocumentType::class)]
    private DocumentType $type;

    /**
     * Current ISO code in force. Null until assigned. For new documents the system generates
     * it following the PC.01.0 scheme; existing codes are preserved as-is.
     */
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $code = null;

    /**
     * Additional/inherited codes the same document has carried over time, kept for audit
     * traceability.
     *
     * @var string[]
     */
    #[ORM\Column(type: Types::JSON)]
    private array $legacyCodes = [];

    /**
     * SGMA process/area the document belongs to. Provisional free text; will become a catalog
     * once the real process map is confirmed.
     */
    #[ORM\Column(length: 120, nullable: true)]
    private ?string $process = null;

    /**
     * ISO 14001 clause (chapters 4-10) this obligation lives under. Drives the supra-structure
     * navigation (its {@see PdcaPhase} is derived from here). Null for documents that are not part
     * of the periodic obligations register.
     */
    #[ORM\Column(nullable: true, enumType: IsoChapter::class)]
    private ?IsoChapter $isoChapter = null;

    /**
     * Manual review state of the obligation (the "¿REVISADO?" column). Complementary to the
     * date-derived urgency traffic-light, never a substitute for it.
     */
    #[ORM\Column(length: 20, enumType: ObligationStatus::class)]
    private ObligationStatus $status = ObligationStatus::PENDING;

    /**
     * Free-text nuance for the status (e.g. "hecho, falta firma de dirección"), as the register
     * carries in its review column.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $statusNote = null;

    /**
     * The built module where this obligation is actually filled in (consumos, NC, formación…).
     * Null means "pending module": the obligation is handled by uploading a file / marking it done
     * until its module is built. Reuses {@see Area} (the catalog of existing modules) on purpose.
     */
    #[ORM\Column(length: 30, nullable: true, enumType: Area::class)]
    private ?Area $linkedArea = null;

    /**
     * What the responsible has to do for this obligation, in plain language. Sourced from the
     * consultant's guide ("Tareas IES La Cabrera"); now that there is no consultant, this is how
     * the app guides the staff. Shown as on-screen help on the obligation.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $instructions = null;

    /**
     * Role responsible for keeping this document up to date. The actual people are the users
     * holding that role (several co-responsibles are allowed).
     */
    #[ORM\ManyToOne(targetEntity: Role::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Role $responsibleRole = null;

    /**
     * Minimum retention in years (procedures: indefinite => null; records: typically 3).
     */
    #[ORM\Column(nullable: true)]
    private ?int $retentionYears = null;

    /** @var Collection<int, DocumentVersion> */
    #[ORM\OneToMany(targetEntity: DocumentVersion::class, mappedBy: 'document', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $versions;

    /** @var Collection<int, ScheduledAlert> */
    #[ORM\OneToMany(targetEntity: ScheduledAlert::class, mappedBy: 'document', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $alerts;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->versions = new ArrayCollection();
        $this->alerts = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    /**
     * Keeps {@see $updatedAt} in sync on every persist/update.
     */
    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * Returns the version currently in force: the approved version with the highest revision
     * number, or null if none has been approved yet.
     *
     * NOTE: iterates the versions collection, triggering lazy-loading if it is not yet
     * initialized. To compute the current version for many documents at once, eager-load the
     * versions with a JOIN to avoid an N+1 query.
     *
     * @return DocumentVersion|null the in-force version, or null when there is none
     */
    public function getCurrentVersion(): ?DocumentVersion
    {
        $current = null;
        foreach ($this->versions as $version) {
            if (!$version->getStatus()->isInForce()) {
                continue;
            }
            if (null === $current || $version->getRevisionNumber() > $current->getRevisionNumber()) {
                $current = $version;
            }
        }

        return $current;
    }

    /**
     * The date-derived urgency of this obligation on the given date: the most urgent of all its
     * review cadences. Event-driven alerts never count as overdue; an obligation with no fixed
     * cadence is {@see ObligationUrgency::EVENT_DRIVEN}, and one with no alerts at all is on track.
     *
     * NOTE: iterates the alerts collection. To classify many obligations at once, eager-load the
     * alerts with a JOIN (see {@see \App\Repository\DocumentRepository::findObligations()}) to
     * avoid an N+1 query.
     *
     * @param \DateTimeImmutable $on       reference date (today)
     * @param int                $soonDays how many days ahead still count as "due soon"
     *
     * @return ObligationUrgency the worst-case urgency across the obligation's cadences
     */
    public function dueStatus(\DateTimeImmutable $on, int $soonDays = 30): ObligationUrgency
    {
        $soonLimit = $on->modify(sprintf('+%d days', $soonDays));

        $worst = null;
        foreach ($this->alerts as $alert) {
            $urgency = $this->urgencyOf($alert, $on, $soonLimit);
            if (null === $worst || $urgency->isMoreUrgentThan($worst)) {
                $worst = $urgency;
            }
        }

        return $worst ?? ObligationUrgency::ON_TRACK;
    }

    /**
     * Urgency contributed by a single alert: event-driven alerts have no due date, the rest are
     * classified against today and the "due soon" window.
     */
    private function urgencyOf(ScheduledAlert $alert, \DateTimeImmutable $on, \DateTimeImmutable $soonLimit): ObligationUrgency
    {
        if (AlertFrequency::ON_EVENT === $alert->getFrequency()) {
            return ObligationUrgency::EVENT_DRIVEN;
        }

        $due = $alert->getNextDueDate();
        if ($due < $on) {
            return ObligationUrgency::OVERDUE;
        }

        return $due <= $soonLimit ? ObligationUrgency::DUE_SOON : ObligationUrgency::ON_TRACK;
    }

    /**
     * The earliest fixed-cadence review due date among this obligation's alerts, or null when it
     * is purely event-driven (no scheduled date). Used to show "next review" in the day-to-day view.
     *
     * @return \DateTimeImmutable|null the soonest due date, or null if none is scheduled
     */
    public function nextReviewDate(): ?\DateTimeImmutable
    {
        $earliest = null;
        foreach ($this->alerts as $alert) {
            if (AlertFrequency::ON_EVENT === $alert->getFrequency()) {
                continue;
            }
            $due = $alert->getNextDueDate();
            if (null === $earliest || $due < $earliest) {
                $earliest = $due;
            }
        }

        return $earliest;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getType(): DocumentType
    {
        return $this->type;
    }

    public function setType(DocumentType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): static
    {
        $this->code = $code;

        return $this;
    }

    /**
     * @return string[]
     */
    public function getLegacyCodes(): array
    {
        return $this->legacyCodes;
    }

    /**
     * @param string[] $legacyCodes
     */
    public function setLegacyCodes(array $legacyCodes): static
    {
        $this->legacyCodes = array_values($legacyCodes);

        return $this;
    }

    public function getProcess(): ?string
    {
        return $this->process;
    }

    public function setProcess(?string $process): static
    {
        $this->process = $process;

        return $this;
    }

    public function getIsoChapter(): ?IsoChapter
    {
        return $this->isoChapter;
    }

    public function setIsoChapter(?IsoChapter $isoChapter): static
    {
        $this->isoChapter = $isoChapter;

        return $this;
    }

    /**
     * The PDCA phase of this obligation, derived from its ISO chapter (null when no chapter is set).
     *
     * @return PdcaPhase|null the phase, or null for non-obligation documents
     */
    public function getPhase(): ?PdcaPhase
    {
        return $this->isoChapter?->phase();
    }

    public function getStatus(): ObligationStatus
    {
        return $this->status;
    }

    public function setStatus(ObligationStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getStatusNote(): ?string
    {
        return $this->statusNote;
    }

    public function setStatusNote(?string $statusNote): static
    {
        $this->statusNote = $statusNote;

        return $this;
    }

    public function getLinkedArea(): ?Area
    {
        return $this->linkedArea;
    }

    public function setLinkedArea(?Area $linkedArea): static
    {
        $this->linkedArea = $linkedArea;

        return $this;
    }

    public function getInstructions(): ?string
    {
        return $this->instructions;
    }

    public function setInstructions(?string $instructions): static
    {
        $this->instructions = $instructions;

        return $this;
    }

    public function getResponsibleRole(): ?Role
    {
        return $this->responsibleRole;
    }

    public function setResponsibleRole(?Role $responsibleRole): static
    {
        $this->responsibleRole = $responsibleRole;

        return $this;
    }

    public function getRetentionYears(): ?int
    {
        return $this->retentionYears;
    }

    public function setRetentionYears(?int $retentionYears): static
    {
        $this->retentionYears = $retentionYears;

        return $this;
    }

    /**
     * @return Collection<int, DocumentVersion>
     */
    public function getVersions(): Collection
    {
        return $this->versions;
    }

    public function addVersion(DocumentVersion $version): static
    {
        if (!$this->versions->contains($version)) {
            $this->versions->add($version);
            $version->setDocument($this);
        }

        return $this;
    }

    public function removeVersion(DocumentVersion $version): static
    {
        $this->versions->removeElement($version);

        return $this;
    }

    /**
     * @return Collection<int, ScheduledAlert>
     */
    public function getAlerts(): Collection
    {
        return $this->alerts;
    }

    public function addAlert(ScheduledAlert $alert): static
    {
        if (!$this->alerts->contains($alert)) {
            $this->alerts->add($alert);
            $alert->setDocument($this);
        }

        return $this;
    }

    public function removeAlert(ScheduledAlert $alert): static
    {
        $this->alerts->removeElement($alert);

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
