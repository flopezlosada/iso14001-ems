<?php

declare(strict_types=1);

namespace App\Service\Overview;

use App\Enum\Area;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Enriches the system overview of one {@see Area} with its domain-specific pending work, for the few
 * modules that have a real workflow (Riesgos today; Auditorías §9.2 and Revisión por la Dirección
 * §9.3 are the expected next ones). The plain register modules do not implement this: their
 * date-based obligation semaphore is signal enough.
 *
 * Implementations are auto-tagged and consumed by {@see SystemOverviewBuilder}; each one owns exactly
 * one area, so a module never gets two competing signals.
 */
#[AutoconfigureTag('app.module_workflow_contributor')]
interface ModuleWorkflowContributor
{
    /**
     * The single area this contributor speaks for.
     *
     * @return Area the area whose workflow this contributor summarises
     */
    public function area(): Area;

    /**
     * The module's pending-work signal for the given exercise, or null when there is nothing to add
     * on top of the obligation semaphore (e.g. the workflow is complete).
     *
     * @param string $exercise the school year, in "YYYY-YYYY" format
     *
     * @return ModuleWorkflowSignal|null the signal, or null when the workflow adds nothing
     */
    public function summarize(string $exercise): ?ModuleWorkflowSignal;
}
