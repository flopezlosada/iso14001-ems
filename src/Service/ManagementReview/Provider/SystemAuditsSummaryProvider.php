<?php

declare(strict_types=1);

namespace App\Service\ManagementReview\Provider;

use App\Entity\SystemAudit;
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

        $findings = $this->findingsBlock($year);
        if ('' !== $findings) {
            $parts[] = '';
            $parts[] = $findings;
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
     * The non-conformities raised by the year's audits, each with its reference, description, status
     * and resolution plan (the corrective actions, with responsible and due date). Empty string when
     * there are none. This is the detail the management review needs: what was found and how it will
     * be resolved, not just a count.
     *
     * @param int $year the reference year
     *
     * @return string the findings block, or '' if there are no audit non-conformities
     */
    private function findingsBlock(int $year): string
    {
        $findings = $this->nonConformities->findAuditFindingsForYear($year);
        if ([] === $findings) {
            return '';
        }

        $lines = [sprintf('No conformidades detectadas en auditoría: %d.', \count($findings))];
        foreach ($findings as $nc) {
            $lines[] = sprintf(
                '- %s (%s): %s [%s]',
                $nc->getReference(),
                $nc->getOrigin()->label(),
                $nc->getDescription(),
                $nc->getStatus()->label(),
            );

            if ($nc->getCorrectiveActions()->isEmpty()) {
                $lines[] = '    Plan de resolución: pendiente de definir.';
                continue;
            }

            foreach ($nc->getCorrectiveActions() as $action) {
                $responsible = $action->getResponsible();
                $lines[] = sprintf(
                    '    Plan de resolución: %s (responsable: %s; prevista: %s).',
                    $action->getDescription(),
                    null !== $responsible ? $responsible->getFullName() : 'sin asignar',
                    null !== $action->getPlannedDate() ? $action->getPlannedDate()->format('d/m/Y') : 'sin fecha',
                );
            }
        }

        return implode("\n", $lines);
    }
}
