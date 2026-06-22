<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\VersionStatus;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * An immutable-in-spirit revision of a {@see Document}. Revision numbering starts at 0 and
 * increments on each approved change (PC.01.0). Superseded revisions are kept and moved to
 * {@see VersionStatus::OBSOLETE}; they are never physically deleted.
 */
#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
#[ORM\UniqueConstraint(name: 'uniq_document_revision', columns: ['document_id', 'revision_number'])]
class DocumentVersion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Document::class, inversedBy: 'versions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Document $document;

    /**
     * Revision number, starting at 0 for the initial issue.
     */
    #[ORM\Column]
    private int $revisionNumber = 0;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $issueDate;

    #[ORM\Column(enumType: VersionStatus::class)]
    private VersionStatus $status = VersionStatus::DRAFT;

    /**
     * Who authored/edited this revision (display name). Will move to a User reference once
     * authentication is in place.
     */
    #[ORM\Column(length: 180, nullable: true)]
    private ?string $author = null;

    /**
     * Short description of the changes for the document history table.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $changeSummary = null;

    /**
     * Path to the artefact for this revision: the generated PDF for system-generated
     * documents, or the uploaded file for external evidence. Null while still a data-only draft.
     */
    #[ORM\Column(length: 1024, nullable: true)]
    private ?string $storagePath = null;

    /** @var Collection<int, ApprovalEvent> */
    #[ORM\OneToMany(targetEntity: ApprovalEvent::class, mappedBy: 'documentVersion', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $approvalEvents;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->approvalEvents = new ArrayCollection();
        $this->issueDate = new \DateTimeImmutable();
        $this->createdAt = new \DateTimeImmutable();
    }

    /**
     * Whether this revision is the one currently in force.
     *
     * @return bool true if approved and not obsolete
     */
    public function isInForce(): bool
    {
        return $this->status->isInForce();
    }

    /**
     * The most recent approval of this revision (by approval date), for the audit trail. Computed
     * explicitly rather than relying on the collection's iteration order.
     *
     * @return ApprovalEvent|null the latest approval, or null if the revision was never approved
     */
    public function getLatestApproval(): ?ApprovalEvent
    {
        $latest = null;
        foreach ($this->approvalEvents as $event) {
            if (null === $latest || $event->getApprovedAt() > $latest->getApprovedAt()) {
                $latest = $event;
            }
        }

        return $latest;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDocument(): Document
    {
        return $this->document;
    }

    public function setDocument(Document $document): static
    {
        $this->document = $document;

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

    public function getIssueDate(): \DateTimeImmutable
    {
        return $this->issueDate;
    }

    public function setIssueDate(\DateTimeImmutable $issueDate): static
    {
        $this->issueDate = $issueDate;

        return $this;
    }

    public function getStatus(): VersionStatus
    {
        return $this->status;
    }

    public function setStatus(VersionStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getAuthor(): ?string
    {
        return $this->author;
    }

    public function setAuthor(?string $author): static
    {
        $this->author = $author;

        return $this;
    }

    public function getChangeSummary(): ?string
    {
        return $this->changeSummary;
    }

    public function setChangeSummary(?string $changeSummary): static
    {
        $this->changeSummary = $changeSummary;

        return $this;
    }

    public function getStoragePath(): ?string
    {
        return $this->storagePath;
    }

    public function setStoragePath(?string $storagePath): static
    {
        $this->storagePath = $storagePath;

        return $this;
    }

    /**
     * @return Collection<int, ApprovalEvent>
     */
    public function getApprovalEvents(): Collection
    {
        return $this->approvalEvents;
    }

    public function addApprovalEvent(ApprovalEvent $approvalEvent): static
    {
        if (!$this->approvalEvents->contains($approvalEvent)) {
            $this->approvalEvents->add($approvalEvent);
            $approvalEvent->setDocumentVersion($this);
        }

        return $this;
    }

    public function removeApprovalEvent(ApprovalEvent $approvalEvent): static
    {
        $this->approvalEvents->removeElement($approvalEvent);

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
