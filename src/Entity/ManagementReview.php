<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ReviewSectionKey;
use App\Repository\ManagementReviewRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * One school year's management review of the EMS (PG-09.03.00, ISO 14001:2015 §9.3): the
 * RG-09.03.01 register. Holds the meeting metadata, the participating users and the fixed set of
 * review sections, and is approved (signed off) by Direction.
 *
 * Each section's text is written/edited by Direction; input sections may be seeded with a frozen
 * snapshot of other modules' data by {@see \App\Service\ManagementReview\ManagementReviewPrefiller}.
 */
#[ORM\Entity(repositoryClass: ManagementReviewRepository::class)]
#[ORM\Table(name: 'management_review')]
#[ORM\UniqueConstraint(name: 'uniq_management_review_exercise', columns: ['exercise'])]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['exercise'], message: 'Ya existe una revisión por la dirección para este curso.')]
class ManagementReview
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * School year this review belongs to, e.g. "2025-2026" (the RG-09.03.01 is kept per course).
     */
    #[ORM\Column(length: 9)]
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^\d{4}-\d{4}$/', message: 'El curso debe tener el formato AAAA-AAAA, p. ej. 2025-2026.')]
    private string $exercise;

    /**
     * Date the review meeting was held. Null while the review is being prepared.
     */
    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $meetingDate = null;

    /**
     * Users who attended the review (Direction, RSGMA…), recorded as participants of the meeting.
     *
     * @var Collection<int, User>
     */
    #[ORM\ManyToMany(targetEntity: User::class)]
    #[ORM\JoinTable(name: 'management_review_participant')]
    private Collection $participants;

    /**
     * The person (Direction) who approved this review. Null while the review is a draft.
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $approvedBy = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $approvedAt = null;

    /**
     * Storage-relative path of the official PDF sealed when the review was approved. Null while a
     * draft; the PDF is served from here so its certified bytes never change.
     */
    #[ORM\Column(length: 1024, nullable: true)]
    private ?string $storagePath = null;

    /**
     * SHA-256 of the sealed PDF's exact bytes, recorded at approval as tamper-evidence. Null while a
     * draft.
     */
    #[ORM\Column(length: 128, nullable: true)]
    private ?string $integrityHash = null;

    /**
     * Storage-relative path of the PDF signed by Direction with their own certificate (level 1a,
     * AutoFirma "upload the signed PDF"). Optional and attached after approval; additional to the
     * sealed PDF, never replacing it.
     */
    #[ORM\Column(length: 1024, nullable: true)]
    private ?string $signedPdfPath = null;

    /**
     * @var Collection<int, ManagementReviewSection>
     */
    #[ORM\OneToMany(targetEntity: ManagementReviewSection::class, mappedBy: 'review', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $sections;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->participants = new ArrayCollection();
        $this->sections = new ArrayCollection();
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

    public function getExercise(): string
    {
        return $this->exercise;
    }

    public function setExercise(string $exercise): static
    {
        $this->exercise = $exercise;

        return $this;
    }

    public function getMeetingDate(): ?\DateTimeImmutable
    {
        return $this->meetingDate;
    }

    public function setMeetingDate(?\DateTimeImmutable $meetingDate): static
    {
        $this->meetingDate = $meetingDate;

        return $this;
    }

    /**
     * @return Collection<int, User> the users who attended the review
     */
    public function getParticipants(): Collection
    {
        return $this->participants;
    }

    public function addParticipant(User $participant): static
    {
        if (!$this->participants->contains($participant)) {
            $this->participants->add($participant);
        }

        return $this;
    }

    public function removeParticipant(User $participant): static
    {
        $this->participants->removeElement($participant);

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
     * Whether this review has been approved (signed off) by Direction.
     *
     * @return bool true once an approval date has been recorded
     */
    public function isApproved(): bool
    {
        return null !== $this->approvedAt;
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

    public function getIntegrityHash(): ?string
    {
        return $this->integrityHash;
    }

    public function setIntegrityHash(?string $integrityHash): static
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

    /**
     * Whether a PDF signed by Direction (level 1a) has been attached to this review.
     *
     * @return bool true once a signed PDF path has been recorded
     */
    public function isDigitallySigned(): bool
    {
        return null !== $this->signedPdfPath;
    }

    /**
     * @return Collection<int, ManagementReviewSection> the report sections, in presentation order
     */
    public function getSections(): Collection
    {
        return $this->sections;
    }

    public function addSection(ManagementReviewSection $section): static
    {
        if (!$this->sections->contains($section)) {
            $this->sections->add($section);
            $section->setReview($this);
        }

        return $this;
    }

    public function removeSection(ManagementReviewSection $section): static
    {
        $this->sections->removeElement($section);

        return $this;
    }

    /**
     * Returns the section with the given key, or null if this review has none.
     *
     * @param ReviewSectionKey $key the section to look up
     *
     * @return ManagementReviewSection|null the matching section, or null
     */
    public function getSection(ReviewSectionKey $key): ?ManagementReviewSection
    {
        foreach ($this->sections as $section) {
            if ($section->getSectionKey() === $key) {
                return $section;
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
