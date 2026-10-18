<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\Enum\ConsumptionType;
use PHPUnit\Framework\TestCase;

/**
 * Domain invariants of {@see ConsumptionType}: units, which types record a cost and the column
 * order of the official form F-6.1.2.
 */
final class ConsumptionTypeTest extends TestCase
{
    public function testEachTypeHasItsUnit(): void
    {
        self::assertSame('kWh', ConsumptionType::ELECTRICITY->unit());
        self::assertSame('m³', ConsumptionType::WATER->unit());
        self::assertSame('L', ConsumptionType::GASOIL->unit());
        self::assertSame('paquetes', ConsumptionType::PAPER->unit());
        self::assertSame('unidades', ConsumptionType::TONER->unit());
        self::assertSame('unidades', ConsumptionType::INK_CARTRIDGE->unit());
    }

    public function testEachTypeHasASpanishLabel(): void
    {
        self::assertSame('Luz', ConsumptionType::ELECTRICITY->label());
        self::assertSame('Tóner', ConsumptionType::TONER->label());
        self::assertSame('Cartuchos de tinta', ConsumptionType::INK_CARTRIDGE->label());
    }

    public function testCasesFollowTheColumnOrderOfTheForm(): void
    {
        self::assertSame(
            ['Gasoil', 'Luz', 'Agua', 'Papel', 'Tóner', 'Cartuchos de tinta'],
            array_map(static fn (ConsumptionType $type): string => $type->label(), ConsumptionType::cases()),
        );
    }

    public function testOnlyPrintingConsumablesDoNotTrackCost(): void
    {
        self::assertTrue(ConsumptionType::ELECTRICITY->tracksCost());
        self::assertTrue(ConsumptionType::WATER->tracksCost());
        self::assertTrue(ConsumptionType::GASOIL->tracksCost());
        self::assertTrue(ConsumptionType::PAPER->tracksCost());
        self::assertFalse(ConsumptionType::TONER->tracksCost());
        self::assertFalse(ConsumptionType::INK_CARTRIDGE->tracksCost());
    }
}
