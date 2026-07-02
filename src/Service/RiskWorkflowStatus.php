<?php

declare(strict_types=1);

namespace App\Service;

/**
 * The state of the risk-and-opportunity workflow (PC.03.0 / F.08.0) for one exercise: what is done
 * and what is still pending, so the module can guide the work instead of showing a bare inventory.
 *
 * It is a snapshot of pending counts derived from real data (not a linear wizard): the work is not
 * sequential, so each signal stands on its own. Built by {@see RiskWorkflowStatusProvider}.
 */
final readonly class RiskWorkflowStatus
{
    /**
     * @param string $exercise               the school year this snapshot describes ("YYYY-YYYY")
     * @param bool   $dafoReady              whether the DAFO (context, F.06.0) exists for this exercise
     * @param int    $totalItems            number of risks/opportunities registered
     * @param int    $unvalued              risks/opportunities with no valuation for this exercise
     * @param int    $unapproved            valuations of this exercise still pending Direction's approval
     * @param int    $relevantWithoutActions relevant valuations (moderate/critical) with no action plan
     */
    public function __construct(
        public string $exercise,
        public bool $dafoReady,
        public int $totalItems,
        public int $unvalued,
        public int $unapproved,
        public int $relevantWithoutActions,
    ) {
    }

    /**
     * Whether there is nothing pending for this exercise: the DAFO exists, there is at least one item,
     * and every item is valued, approved and (when relevant) has an action plan.
     *
     * @return bool true when no signal is pending
     */
    public function isComplete(): bool
    {
        return $this->dafoReady
            && $this->totalItems > 0
            && 0 === $this->unvalued
            && 0 === $this->unapproved
            && 0 === $this->relevantWithoutActions;
    }
}
