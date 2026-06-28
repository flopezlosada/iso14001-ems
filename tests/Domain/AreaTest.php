<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\Enum\Area;
use App\Enum\PdcaPhase;
use PHPUnit\Framework\TestCase;

/**
 * Every functional area must expose a human label, an existing index route and a PDCA phase, since
 * the obligations cockpit deep-links to {@see Area::indexRoute()} and the navigation menu groups by
 * {@see Area::phase()}. Guards the exhaustiveness of every match() arm as the catalog grows module
 * by module (e.g. the DAFO context module).
 */
final class AreaTest extends TestCase
{
    public function testEveryAreaHasANonEmptyLabelAndRoute(): void
    {
        foreach (Area::cases() as $area) {
            self::assertNotSame('', $area->label());
            self::assertNotSame('', $area->indexRoute());
        }
    }

    public function testEveryAreaMapsToAPhaseAndEveryPhaseIsUsed(): void
    {
        $usedPhases = [];
        foreach (Area::cases() as $area) {
            // A new Area without a phase() arm throws UnhandledMatchError here, not at first render.
            $usedPhases[$area->phase()->value] = true;
        }

        $allPhases = [];
        foreach (PdcaPhase::cases() as $phase) {
            $allPhases[$phase->value] = true;
        }

        ksort($usedPhases);
        ksort($allPhases);
        // Every PDCA folder has at least one area (no empty pillar by construction).
        self::assertSame($allPhases, $usedPhases);
    }

    public function testDafoAreaMapsToItsModule(): void
    {
        self::assertSame('Análisis DAFO', Area::DAFO->label());
        self::assertSame('dafo_index', Area::DAFO->indexRoute());
    }
}
