<?php

declare(strict_types=1);

namespace App\Service\Overview;

use App\Enum\Area;
use App\Enum\ModuleHealth;

/**
 * The overview of one functional {@see Area} on the system dashboard: its health for the current
 * period and the concrete pending step. The card's label and link come from the {@see Area} itself.
 */
final readonly class ModuleOverview
{
    /**
     * @param Area         $area    the module this card describes
     * @param ModuleHealth $health  its health for the current period (drives the semaphore colour)
     * @param string       $summary the concrete pending step, or a reassuring "al día" when nothing is due
     */
    public function __construct(
        public Area $area,
        public ModuleHealth $health,
        public string $summary,
    ) {
    }
}
