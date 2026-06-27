<?php

declare(strict_types=1);

namespace App\Service\ManagementReview\Provider;

use App\Entity\InterestedParty;
use App\Enum\ReviewSectionKey;
use App\Repository\InterestedPartyRepository;
use App\Service\ManagementReview\ExerciseYears;
use App\Service\ManagementReview\SectionSummaryProvider;

/**
 * Summarises the interested parties identified for the course (F.04.0 / PPI), with their
 * needs/expectations and any incidents detected, for the "changes in the needs and expectations of
 * interested parties" section of the management review.
 *
 * The PPI register is keyed by a single calendar year; the centre draws it up for the course's
 * opening year (e.g. course "2025-2026" -> register 2025), so {@see ExerciseYears::startYear} is
 * used rather than the closing year.
 */
final class InterestedPartiesSummaryProvider implements SectionSummaryProvider
{
    public function __construct(
        private readonly InterestedPartyRepository $parties,
    ) {
    }

    public function section(): ReviewSectionKey
    {
        return ReviewSectionKey::INTERESTED_PARTIES;
    }

    public function summarize(string $exercise): string
    {
        $year = ExerciseYears::startYear($exercise);

        // A single query for the year (the entity is flat, no associations), so there is no N+1.
        $parties = $this->parties->findForYear($year);
        if ([] === $parties) {
            return '';
        }

        $lines = array_map(
            static fn (InterestedParty $party): string => sprintf(
                '- %s: %s%s',
                $party->getName(),
                $party->getNeedsAndExpectations(),
                self::incidentsNote($party->getIncidents()),
            ),
            $parties,
        );

        $header = sprintf('Partes interesadas identificadas en %d: %d.', $year, \count($parties));

        return implode("\n", [$header, '', ...$lines]);
    }

    /**
     * Renders the incidents note for a party, if it carries a meaningful one. The real F.04.0 sheet
     * leaves incidents blank or writes "NO" when there is nothing to report, so both are treated as
     * "no incidents" and omitted; any other text is reported.
     *
     * @param string|null $incidents the raw incidents text
     *
     * @return string the " (incidencias: …)" suffix, or an empty string when there is nothing to note
     */
    private static function incidentsNote(?string $incidents): string
    {
        $incidents = trim((string) $incidents);
        if ('' === $incidents || 'NO' === mb_strtoupper($incidents)) {
            return '';
        }

        return sprintf(' (incidencias: %s)', $incidents);
    }
}
