<?php

declare(strict_types=1);

namespace App\Service\Overview;

use App\Enum\ModuleHealth;

/**
 * A module's own assessment of its pending work for a period, contributed by a
 * {@see ModuleWorkflowContributor}. It complements the date-based obligation semaphore with the
 * concrete, domain-specific step that is missing (e.g. "2 sin valorar, 1 sin aprobar"), so a module
 * whose obligation is on schedule but whose workflow is incomplete is not shown as a false green.
 */
final readonly class ModuleWorkflowSignal
{
    /**
     * @param ModuleHealth $health  the health this workflow implies on its own
     * @param string       $summary a short, human-facing description of the concrete pending step
     */
    public function __construct(
        public ModuleHealth $health,
        public string $summary,
    ) {
    }
}
