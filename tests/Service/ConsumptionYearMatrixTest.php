<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\ConsumptionReading;
use App\Enum\ConsumptionType;
use App\Service\ConsumptionYearMatrix;
use PHPUnit\Framework\TestCase;

/**
 * Lookups of the yearly F-6.1.2 grid: cells by (month, type), the month's observations and the
 * column totals. Readings mirror the real register, where most months have no notes and some
 * utilities are only recorded every few months.
 */
final class ConsumptionYearMatrixTest extends TestCase
{
    private function reading(ConsumptionType $type, int $month, string $quantity, ?string $notes = null): ConsumptionReading
    {
        return (new ConsumptionReading())
            ->setType($type)
            ->setPeriodYear(2026)
            ->setPeriodMonth($month)
            ->setQuantity($quantity)
            ->setNotes($notes);
    }

    public function testFindsEachReadingByMonthAndTypeAndLeavesTheRestEmpty(): void
    {
        $january = $this->reading(ConsumptionType::GASOIL, 1, '8700.000');
        $march = $this->reading(ConsumptionType::WATER, 3, '168.000');

        $matrix = ConsumptionYearMatrix::fromReadings([$january, $march], []);

        self::assertSame($january, $matrix->reading(1, ConsumptionType::GASOIL));
        self::assertSame($march, $matrix->reading(3, ConsumptionType::WATER));
        self::assertNull($matrix->reading(1, ConsumptionType::WATER));
        self::assertNull($matrix->reading(2, ConsumptionType::GASOIL));
    }

    public function testMonthNotesKeepTheirUtilityAreNonBlankAndInColumnOrder(): void
    {
        $matrix = ConsumptionYearMatrix::fromReadings([
            $this->reading(ConsumptionType::WATER, 1, '202.000', 'Cambio comercializadora'),
            $this->reading(ConsumptionType::GASOIL, 1, '8700.000', 'Hechos 2 pedidos en enero'),
            $this->reading(ConsumptionType::ELECTRICITY, 1, '4642.000', ' Cambio comercializadora '),
            $this->reading(ConsumptionType::PAPER, 1, '300.000', '   '),
            $this->reading(ConsumptionType::TONER, 1, '3.000'),
        ], []);

        // Column order (gasoil, electricity, water); each note keeps its utility, so the same text on two
        // utilities stays twice; blanks and nulls are dropped and the text is trimmed.
        self::assertSame([
            ['type' => ConsumptionType::GASOIL, 'note' => 'Hechos 2 pedidos en enero'],
            ['type' => ConsumptionType::ELECTRICITY, 'note' => 'Cambio comercializadora'],
            ['type' => ConsumptionType::WATER, 'note' => 'Cambio comercializadora'],
        ], $matrix->notes(1));
        self::assertSame([], $matrix->notes(2));
    }

    public function testTotalIsNullForAUtilityWithoutReadings(): void
    {
        $totals = ['electricity' => ['quantity' => '22335.000', 'cost' => '4755.34'], 'toner' => ['quantity' => '12.000', 'cost' => null]];

        $matrix = ConsumptionYearMatrix::fromReadings([], $totals);

        self::assertSame(['quantity' => '22335.000', 'cost' => '4755.34'], $matrix->total(ConsumptionType::ELECTRICITY));
        self::assertSame(['quantity' => '12.000', 'cost' => null], $matrix->total(ConsumptionType::TONER));
        self::assertNull($matrix->total(ConsumptionType::GASOIL));
    }
}
