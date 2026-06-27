<?php

declare(strict_types=1);

namespace App\Service\ManagementReview\Provider;

use App\Entity\SystemAudit;
use App\Enum\NonConformityOrigin;
use App\Enum\ReviewSectionKey;
use App\Repository\NonConformityRepository;
use App\Repository\SystemAuditRepository;
use App\Service\ManagementReview\ExerciseYears;
use App\Service\ManagementReview\SectionSummaryProvider;

/**
 * Summarises the management-system audits of the course's closing year (PC.09.0) and the
 * non-conformities they raised, for the "audit results" section of the management review.
 *
 * Audits are filed by calendar year and conducted towards the end of the course, so the closing
 * year is used (e.g. course "2025-2026" -> audits filed under 2026). The non-conformity counts come
 * from the non-conformity module (by audit origin), the single place findings are recorded.
 */
final class SystemAuditsSummaryProvider implements SectionSummaryProvider
{
    public function __construct(
        private readonly SystemAuditRepository $audits,
        private readonly NonConformityRepository $nonConformities,
    ) {
    }

    public function section(): ReviewSectionKey
    {
        return ReviewSectionKey::AUDIT_RESULTS;
    }

    public function summarize(string $exercise): string
    {
        $year = ExerciseYears::endYear($exercise);

        $audits = $this->audits->findForYear($year);
        if ([] === $audits) {
            return '';
        }

        $lines = array_map(
            static fn (SystemAudit $audit): string => self::auditLine($audit),
            $audits,
        );

        $header = sprintf('Auditorías del año %d: %d.', $year, \count($audits));
        $parts = [$header, '', ...$lines];

        $findingsLine = $this->findingsLine($year);
        if ('' !== $findingsLine) {
            $parts[] = '';
            $parts[] = $findingsLine;
        }

        return implode("\n", $parts);
    }

    /**
     * One bullet describing an audit: type, date, auditor and conclusions when present.
     *
     * @param SystemAudit $audit the audit
     *
     * @return string the bullet line
     */
    private static function auditLine(SystemAudit $audit): string
    {
        $when = null !== $audit->getConductedOn() ? $audit->getConductedOn()->format('d/m/Y') : 'sin fecha';
        $conclusions = trim((string) $audit->getConclusions());

        return sprintf(
            '- Auditoría %s (%s), %s.%s',
            $audit->getType()->label(),
            $when,
            $audit->getAuditor(),
            '' !== $conclusions ? ' Conclusiones: '.$conclusions : '',
        );
    }

    /**
     * A line with the count of audit non-conformities of the year, broken down by origin, or an
     * empty string when there are none.
     *
     * @param int $year the reference year
     *
     * @return string the findings line, or '' if there are no audit non-conformities
     */
    private function findingsLine(int $year): string
    {
        $counts = $this->nonConformities->countAuditFindingsByOriginForYear($year);
        $internal = $counts[NonConformityOrigin::INTERNAL_AUDIT->value] ?? 0;
        $external = $counts[NonConformityOrigin::EXTERNAL_AUDIT->value] ?? 0;
        $total = $internal + $external;

        if (0 === $total) {
            return '';
        }

        return sprintf(
            'No conformidades detectadas en auditoría: %d (interna: %d, externa: %d).',
            $total,
            $internal,
            $external,
        );
    }
}
