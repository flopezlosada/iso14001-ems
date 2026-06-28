<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ReviewSectionKey;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * One section of a {@see ManagementReview} (RG-09.03.01). The {@see $content} is the text that is
 * reviewed and signed off by Direction; {@see $generatedSnapshot} keeps a frozen copy of whatever
 * a {@see \App\Service\ManagementReview\SectionSummaryProvider} pre-filled, so the figures shown in
 * the signed report never drift from a later state of the source module.
 */
#[ORM\Entity]
#[ORM\Table(name: 'management_review_section')]
#[ORM\UniqueConstraint(name: 'uniq_review_section_key', columns: ['review_id', 'section_key'])]
class ManagementReviewSection
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ManagementReview::class, inversedBy: 'sections')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ManagementReview $review;

    #[ORM\Column(name: 'section_key', length: 40, enumType: ReviewSectionKey::class)]
    private ReviewSectionKey $sectionKey;

    /**
     * Presentation order within the report, taken from the order of {@see ReviewSectionKey}.
     */
    #[ORM\Column(type: Types::SMALLINT)]
    private int $position = 0;

    /**
     * For an output (decision) section, the verdict Direction picked from its closed set of options
     * ({@see ReviewSectionKey::decisionOptions()}); the {@see $content} then holds the detail. Null
     * for input sections and for decisions not yet valued.
     */
    #[ORM\Column(length: 80, nullable: true)]
    private ?string $decision = null;

    /**
     * The text reviewed and signed by Direction (pre-filled from a snapshot when available,
     * then freely edited). For a decision section it is the detail behind the {@see $decision}.
     * Null until first saved.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $content = null;

    /**
     * Frozen copy of the text a summary provider produced when the review was generated. Kept for
     * traceability/diffing; null for sections that have no provider.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $generatedSnapshot = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReview(): ManagementReview
    {
        return $this->review;
    }

    public function setReview(ManagementReview $review): static
    {
        $this->review = $review;

        return $this;
    }

    public function getSectionKey(): ReviewSectionKey
    {
        return $this->sectionKey;
    }

    public function setSectionKey(ReviewSectionKey $sectionKey): static
    {
        $this->sectionKey = $sectionKey;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function getDecision(): ?string
    {
        return $this->decision;
    }

    public function setDecision(?string $decision): static
    {
        $this->decision = $decision;

        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function getGeneratedSnapshot(): ?string
    {
        return $this->generatedSnapshot;
    }

    public function setGeneratedSnapshot(?string $generatedSnapshot): static
    {
        $this->generatedSnapshot = $generatedSnapshot;

        return $this;
    }
}
