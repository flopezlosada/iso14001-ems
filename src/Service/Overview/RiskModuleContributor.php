<?php

declare(strict_types=1);

namespace App\Service\Overview;

use App\Enum\Area;
use App\Enum\ModuleHealth;
use App\Service\RiskWorkflowStatusProvider;

/**
 * Surfaces the Riesgos y Oportunidades (PC.03.0 / F.08.0) pending work on the system overview,
 * reusing the module's own {@see RiskWorkflowStatusProvider}. This is what stops a risk module whose
 * obligation happens to be on schedule from showing as a false green while valuations, approvals or
 * action plans are still missing.
 *
 * The workflow is never "overdue" here (that is the date-based obligation semaphore's job); an
 * incomplete workflow is always {@see ModuleHealth::ATTENTION}, described by the concrete steps left.
 */
final readonly class RiskModuleContributor implements ModuleWorkflowContributor
{
    public function __construct(private RiskWorkflowStatusProvider $provider)
    {
    }

    public function area(): Area
    {
        return Area::RISK_OPPORTUNITY;
    }

    public function summarize(string $exercise): ?ModuleWorkflowSignal
    {
        $status = $this->provider->for($exercise);
        if ($status->isComplete()) {
            return null; // nothing to add: the obligation semaphore already shows it as OK
        }

        $parts = [];
        if (!$status->dafoReady) {
            $parts[] = 'falta el DAFO del curso';
        } elseif (0 === $status->totalItems) {
            $parts[] = 'sin riesgos identificados';
        }
        if ($status->unvalued > 0) {
            $parts[] = sprintf('%d sin valorar', $status->unvalued);
        }
        if ($status->unapproved > 0) {
            $parts[] = sprintf('%d sin aprobar', $status->unapproved);
        }
        if ($status->relevantWithoutActions > 0) {
            $parts[] = sprintf('%d sin plan de acción', $status->relevantWithoutActions);
        }

        // Defensive: an "incomplete" status with no describable step adds nothing.
        if ([] === $parts) {
            return null;
        }

        return new ModuleWorkflowSignal(ModuleHealth::ATTENTION, ucfirst(implode(', ', $parts)));
    }
}
