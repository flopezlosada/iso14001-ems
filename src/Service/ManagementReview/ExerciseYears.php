<?php

declare(strict_types=1);

namespace App\Service\ManagementReview;

/**
 * Translates a school-year string ("2025-2026") into the calendar year used by the modules that
 * store their data by year (aspects, non-conformities, indicators…). The closing year is used,
 * matching how those modules are reviewed at the end of the course.
 */
final class ExerciseYears
{
    /**
     * The closing calendar year of a school year, e.g. "2025-2026" -> 2026.
     *
     * @param string $exercise the school year, e.g. "2025-2026"
     *
     * @return int the closing year
     */
    public static function endYear(string $exercise): int
    {
        // Normalise the separator first: the canonical form uses a hyphen ("2025-2026"), but some
        // data carries a slash ("2025/2026") — without this, end() would return the whole string.
        $parts = explode('-', str_replace('/', '-', $exercise));

        return (int) end($parts);
    }

    /**
     * The opening calendar year of a school year, e.g. "2025-2026" -> 2025. Used by modules whose
     * yearly register is keyed by the year the course starts (interested parties, F.04.0) rather
     * than by its closing year.
     *
     * @param string $exercise the school year, e.g. "2025-2026"
     *
     * @return int the opening year
     */
    public static function startYear(string $exercise): int
    {
        // Same separator normalisation as endYear: accept both "2025-2026" and "2025/2026".
        $parts = explode('-', str_replace('/', '-', $exercise));

        return (int) $parts[0];
    }
}
