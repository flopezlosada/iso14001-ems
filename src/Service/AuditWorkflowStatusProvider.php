<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\AuditStatus;
use App\Enum\NonConformityStatus;
use App\Repository\NonConformityRepository;
use App\Repository\SystemAuditRepository;

/**
 * Computes the {@see AuditWorkflowStatus} of a programme year from the existing data, without an
 * N+1: the year's internal audit is one lookup and, only when it exists, its non-conformities are
 * read in one query to derive the finding counts.
 */
final readonly class AuditWorkflowStatusProvider
{
    public function __construct(
        private SystemAuditRepository $audits,
        private NonConformityRepository $nonConformities,
    ) {
    }

    /**
     * Builds the internal-audit workflow status for the given year.
     *
     * @param int $year the programme year
     *
     * @return AuditWorkflowStatus the pending-work snapshot for that year
     */
    public function for(int $year): AuditWorkflowStatus
    {
        $audit = $this->audits->findInternalForYear($year);
        if (null === $audit) {
            return new AuditWorkflowStatus($year, null, false, false, false, 0, 0);
        }

        $findings = $this->nonConformities->findByAudit($audit);
        $open = 0;
        foreach ($findings as $finding) {
            if (NonConformityStatus::CLOSED !== $finding->getStatus()) {
                ++$open;
            }
        }

        return new AuditWorkflowStatus(
            $year,
            $audit->getId(),
            true,
            null !== $audit->getConductedOn(),
            AuditStatus::CLOSED === $audit->status(),
            \count($findings),
            $open,
        );
    }
}
