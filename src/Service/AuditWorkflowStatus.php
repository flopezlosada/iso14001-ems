<?php

declare(strict_types=1);

namespace App\Service;

/**
 * The state of the internal-audit workflow (PC.09.0, ISO 14001:2015 §9.2) for one programme year:
 * what is done and what is still pending, so the module can guide the work (plan → conduct →
 * register findings → close) instead of showing a bare list of audits.
 *
 * It focuses on the year's internal audit — the recurring §9.2 obligation — because the external
 * (certification) audit is not scheduled by the centre. It is a snapshot of pending signals derived
 * from real data (not a linear wizard). Built by {@see AuditWorkflowStatusProvider}.
 */
final readonly class AuditWorkflowStatus
{
    /**
     * @param int      $year            the programme year this snapshot describes
     * @param int|null $internalAuditId the id of the year's internal audit (to link to it), or null if none
     * @param bool     $planned         whether the year's internal audit has been registered (planned)
     * @param bool     $conducted       whether it has a conduction date (has been carried out)
     * @param bool     $closed          whether its conclusions have been written (it is closed)
     * @param int      $findingsCount   number of non-conformities raised in it
     * @param int      $openFindings    of those, how many are still not closed
     */
    public function __construct(
        public int $year,
        public ?int $internalAuditId,
        public bool $planned,
        public bool $conducted,
        public bool $closed,
        public int $findingsCount,
        public int $openFindings,
    ) {
    }

    /**
     * Whether the year's internal-audit cycle is fully done: it was planned, conducted and closed,
     * and none of its non-conformities remains open.
     *
     * @return bool true when no signal is pending
     */
    public function isComplete(): bool
    {
        return $this->planned
            && $this->conducted
            && $this->closed
            && 0 === $this->openFindings;
    }
}
