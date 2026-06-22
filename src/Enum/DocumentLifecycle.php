<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Lifecycle state of a whole document in the register, distinct from its compliance
 * {@see ObligationStatus} and from a single revision's {@see VersionStatus}.
 *
 * Documents are never deleted (audit trail / non-repudiation, PC.01.0): a document created by
 * mistake is CANCELLED with a reason, one that no longer applies is ARCHIVED (retired but still
 * visible to the auditor). Both keep their history; only ACTIVE documents are part of the live SGA.
 */
enum DocumentLifecycle: string
{
    case ACTIVE = 'active';
    case CANCELLED = 'cancelled';
    case ARCHIVED = 'archived';

    /**
     * Whether the document is live (part of the current SGA), as opposed to cancelled or archived.
     *
     * @return bool true only for ACTIVE
     */
    public function isActive(): bool
    {
        return self::ACTIVE === $this;
    }

    /**
     * Human-facing label (Spanish, the application's UI language).
     *
     * @return string the display name
     */
    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Activo',
            self::CANCELLED => 'Anulado',
            self::ARCHIVED => 'Archivado',
        };
    }
}
