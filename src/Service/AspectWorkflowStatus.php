<?php

declare(strict_types=1);

namespace App\Service;

/**
 * The state of the environmental-aspects workflow (PG-06.01 / RG-06.01.01) for one year: what is
 * done and what is still pending, so the module can guide the yearly work instead of showing a bare
 * inventory.
 *
 * It is a snapshot of counts derived from real data (not a linear wizard): identifying aspects and
 * evaluating them are related but each signal stands on its own. Built by
 * {@see AspectWorkflowStatusProvider}.
 */
final readonly class AspectWorkflowStatus
{
    /**
     * @param int $year        the calendar year this snapshot describes
     * @param int $totalActive number of active aspects registered (the catalogue to evaluate)
     * @param int $unevaluated active aspects with no evaluation for this year
     * @param int $significant aspects evaluated as significant this year
     */
    public function __construct(
        public int $year,
        public int $totalActive,
        public int $unevaluated,
        public int $significant,
    ) {
    }

    /**
     * Whether there is nothing pending for this year: there is at least one active aspect and every
     * one of them has been evaluated for the year.
     *
     * @return bool true when no evaluation is pending
     */
    public function isComplete(): bool
    {
        return $this->totalActive > 0 && 0 === $this->unevaluated;
    }
}
