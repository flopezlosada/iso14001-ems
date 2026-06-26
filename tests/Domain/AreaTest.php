<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\Enum\Area;
use PHPUnit\Framework\TestCase;

/**
 * Every functional area must expose a human label and an existing index route, since the
 * obligations cockpit deep-links to {@see Area::indexRoute()}. Guards the exhaustiveness of both
 * match() arms as the catalog grows module by module (e.g. the DAFO context module).
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

    public function testDafoAreaMapsToItsModule(): void
    {
        self::assertSame('Análisis DAFO', Area::DAFO->label());
        self::assertSame('dafo_index', Area::DAFO->indexRoute());
    }
}
