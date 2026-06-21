<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\Enum\ConsumptionType;
use PHPUnit\Framework\TestCase;

/**
 * Domain invariants of {@see ConsumptionType}: units and which types record a cost.
 */
final class ConsumptionTypeTest extends TestCase
{
    public function testEachTypeHasItsUnit(): void
    {
        self::assertSame('kWh', ConsumptionType::ELECTRICITY->unit());
        self::assertSame('m³', ConsumptionType::WATER->unit());
        self::assertSame('L', ConsumptionType::GASOIL->unit());
        self::assertSame('packages', ConsumptionType::PAPER->unit());
        self::assertSame('cartridges', ConsumptionType::TONER->unit());
    }

    public function testOnlyTonerDoesNotTrackCost(): void
    {
        self::assertTrue(ConsumptionType::ELECTRICITY->tracksCost());
        self::assertTrue(ConsumptionType::WATER->tracksCost());
        self::assertTrue(ConsumptionType::GASOIL->tracksCost());
        self::assertTrue(ConsumptionType::PAPER->tracksCost());
        self::assertFalse(ConsumptionType::TONER->tracksCost());
    }
}
