<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A traceable approval of a specific {@see DocumentVersion}.
 *
 * This is the single mechanism that satisfies both the ISO requirement (review and approval,
 * clause 7.5) and non-repudiation: it records who approved, when, the exact version, and an
 * integrity hash of the approved content. A qualified digital signature is OPTIONAL and, when
 * provided, is attached as a signed PDF ({@see $signedPdfPath}) — the "upload the signed PDF"
 * approach (level 1a). No separate signature subsystem is needed.
 */
#[ORM\Entity]
class ApprovalEvent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: DocumentVersion::class, inversedBy: 'approvalEvents')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private DocumentVersion $documentVersion;

    /**
     * The person who approved this version (recorded for non-repudiation).
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $approver;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $approvedAt;

    /**
     * Hash (e.g. SHA-256) of the approved version's content, so any later tampering is
     * detectable. Guarantees the approval cannot be altered retroactively without a trace.
     */
    #[ORM\Column(length: 128)]
    private string $integrityHash;

    /**
     * Path to the PDF signed by the approver with their own certificate (FNMT / DNIe).
     * Null when only the in-app approval (level 0) is used.
     */
    #[ORM\Column(length: 1024, nullable: true)]
    private ?string $signedPdfPath = null;

    public function __construct()
    {
        $this->approvedAt = new \DateTimeImmutable();
    }

    /**
     * Whether this approval carries a qualified digital signature (an attached signed PDF).
     *
     * @return bool true if a signed PDF is attached (level 1a), false for approval-only (level 0)
     */
    public function isDigitallySigned(): bool
    {
        return null !== $this->signedPdfPath;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDocumentVersion(): DocumentVersion
    {
        return $this->documentVersion;
    }

    public function setDocumentVersion(DocumentVersion $documentVersion): static
    {
        $this->documentVersion = $documentVersion;

        return $this;
    }

    public function getApprover(): User
    {
        return $this->approver;
    }

    public function setApprover(User $approver): static
    {
        $this->approver = $approver;

        return $this;
    }

    public function getApprovedAt(): \DateTimeImmutable
    {
        return $this->approvedAt;
    }

    public function setApprovedAt(\DateTimeImmutable $approvedAt): static
    {
        $this->approvedAt = $approvedAt;

        return $this;
    }

    public function getIntegrityHash(): string
    {
        return $this->integrityHash;
    }

    public function setIntegrityHash(string $integrityHash): static
    {
        $this->integrityHash = $integrityHash;

        return $this;
    }

    public function getSignedPdfPath(): ?string
    {
        return $this->signedPdfPath;
    }

    public function setSignedPdfPath(?string $signedPdfPath): static
    {
        $this->signedPdfPath = $signedPdfPath;

        return $this;
    }
}
