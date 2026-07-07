<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Lifecycle state of a management review (PG-09.03.00 / RG-09.03.01, ISO 14001:2015 §9.3), derived
 * from the {@see \App\Entity\ManagementReview}'s own data rather than stored: it is a draft while
 * being prepared, approved once Direction signs it off (sealing the official PDF) and shown as
 * signed once a level-1a signed PDF is also attached.
 *
 * It is not a stored field: the approval and signature timestamps already tell the whole story.
 */
enum ManagementReviewStatus: string
{
    case DRAFT = 'draft';
    case APPROVED = 'approved';
    case SIGNED = 'signed';

    /**
     * Human-facing label (Spanish, the application's UI language).
     *
     * @return string the display name
     */
    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Borrador',
            self::APPROVED => 'Aprobada',
            self::SIGNED => 'Firmada',
        };
    }

    /**
     * CSS badge class on the shared semantic scale: a draft is neutral (grey); an approved or signed
     * review is done (green). Signature is an extra step over an already-valid approval, so both
     * approved states share the "done" colour and differ only in wording.
     *
     * @return string the badge modifier class
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::DRAFT => 'badge--draft',
            self::APPROVED, self::SIGNED => 'badge--success',
        };
    }
}
