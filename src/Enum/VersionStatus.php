<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Lifecycle status of a single document version.
 *
 * Versions are never physically deleted: when superseded they move to OBSOLETE and are
 * archived, as required by the documented-information procedure (PC.01.0).
 */
enum VersionStatus: string
{
    case DRAFT = 'draft';
    case IN_REVIEW = 'in_review';
    case APPROVED = 'approved';
    case OBSOLETE = 'obsolete';

    /**
     * Whether this status represents the version currently in force.
     *
     * @return bool true only for an approved (non-obsolete) version
     */
    public function isInForce(): bool
    {
        return $this === self::APPROVED;
    }
}
