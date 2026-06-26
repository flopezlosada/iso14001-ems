<?php

declare(strict_types=1);

namespace App\Service\ManagementReview;

use App\Enum\ReviewSectionKey;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Produces the pre-filled text for one section of a management review (RG-09.03.01) from another
 * module's data for a given course.
 *
 * Implementations are discovered automatically (tagged), so a new provider can be added for a
 * section without touching the prefiller or the entity (Open/Closed). A section with no provider
 * is left empty for Direction to fill in by hand.
 */
#[AutoconfigureTag('app.review_section_provider')]
interface SectionSummaryProvider
{
    /**
     * The review section this provider fills.
     *
     * @return ReviewSectionKey the section covered
     */
    public function section(): ReviewSectionKey;

    /**
     * Builds a human-readable summary of the source module's data for the given course. The result
     * is frozen as a snapshot at generation time, so the signed report never drifts from a later
     * state of the source module.
     *
     * @param string $exercise the school year, e.g. "2025-2026"
     *
     * @return string the summary text (Spanish); empty string if there is nothing to report
     */
    public function summarize(string $exercise): string;
}
