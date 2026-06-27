<?php

declare(strict_types=1);

namespace App\Service\ManagementReview\Provider;

use App\Entity\Communication;
use App\Enum\ReviewSectionKey;
use App\Repository\CommunicationRepository;
use App\Service\ManagementReview\ExerciseYears;
use App\Service\ManagementReview\SectionSummaryProvider;

/**
 * Summarises the communications relevant to interested parties recorded during the course
 * (RG-07.04.00, §7.4), with complaints called out, for the management review's "comunicaciones
 * pertinentes de las partes interesadas, incluidas quejas" section (§9.3.2).
 *
 * Communications are a flat event log keyed by date; the course is reviewed at its close, so the
 * closing calendar year is used ({@see ExerciseYears::endYear}), consistent with the other
 * date-driven modules. Only the communications that are pertinent to interested parties are
 * reported: those linked to an interested party, plus every complaint (a complaint is relevant
 * whether or not the complainant was linked to a known party).
 */
final class InterestedPartyCommsSummaryProvider implements SectionSummaryProvider
{
    public function __construct(
        private readonly CommunicationRepository $communications,
    ) {
    }

    public function section(): ReviewSectionKey
    {
        return ReviewSectionKey::INTERESTED_PARTY_COMMS;
    }

    public function summarize(string $exercise): string
    {
        $year = ExerciseYears::endYear($exercise);

        // A single query for the year, with the interested party eager-joined (no N+1).
        $relevant = array_filter(
            $this->communications->findForYear($year),
            static fn (Communication $c): bool => $c->isComplaint() || null !== $c->getInterestedParty(),
        );
        if ([] === $relevant) {
            return '';
        }

        $complaints = array_filter($relevant, static fn (Communication $c): bool => $c->isComplaint());

        $header = sprintf(
            'Comunicaciones pertinentes de partes interesadas en %d: %d (de ellas, %d %s).',
            $year,
            \count($relevant),
            \count($complaints),
            1 === \count($complaints) ? 'queja' : 'quejas',
        );

        $lines = array_map(self::line(...), $relevant);

        return implode("\n", [$header, '', ...$lines]);
    }

    /**
     * Renders one communication as a summary line: date, category, subject, the related party (if
     * any) and, for complaints, whether a response has been recorded.
     *
     * @param Communication $c the communication to render
     *
     * @return string the "- …" summary line
     */
    private static function line(Communication $c): string
    {
        $party = $c->getInterestedParty();

        return sprintf(
            '- %s [%s] %s%s%s',
            $c->getOccurredOn()->format('d/m/Y'),
            $c->getCategory()->label(),
            $c->getSubject(),
            null !== $party ? sprintf(' — %s', $party->getName()) : '',
            $c->isComplaint()
                ? (null !== $c->getResponse() && '' !== trim($c->getResponse()) ? ' (respondida)' : ' (pendiente de respuesta)')
                : '',
        );
    }
}
