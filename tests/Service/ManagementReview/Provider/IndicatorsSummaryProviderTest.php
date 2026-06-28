<?php

declare(strict_types=1);

namespace App\Tests\Service\ManagementReview\Provider;

use App\Entity\Indicator;
use App\Entity\IndicatorMeasurement;
use App\Repository\IndicatorRepository;
use App\Service\ManagementReview\Provider\IndicatorsSummaryProvider;
use PHPUnit\Framework\TestCase;

final class IndicatorsSummaryProviderTest extends TestCase
{
    /**
     * @param list<array{int, int, string, bool}> $measurements rows of [year, month, value, breached]
     */
    private function indicator(string $name, ?string $reference, array $measurements): Indicator
    {
        $indicator = (new Indicator())->setName($name)->setReferenceValue($reference);
        foreach ($measurements as [$year, $month, $value, $breached]) {
            $indicator->addMeasurement(
                (new IndicatorMeasurement())->setYear($year)->setMonth($month)->setValue($value)->setBreached($breached),
            );
        }

        return $indicator;
    }

    public function testNamesEachMeasuredIndicatorAndTrimsDecimals(): void
    {
        $repo = $this->createMock(IndicatorRepository::class);
        // Course "2025-2026" -> closing year 2026 (ExerciseYears::endYear). The 2025 reading is ignored.
        $repo->method('findAllWithMeasurements')->willReturn([
            $this->indicator('Consumo eléctrico por estudiante', '≤ 30 kWh/estudiante', [
                [2026, 1, '33.000', true],
                [2026, 2, '28.000', false],
                [2025, 12, '40.000', true],
            ]),
            $this->indicator('Consumo de agua', null, [
                [2026, 1, '150.000', false],
            ]),
        ]);

        $summary = (new IndicatorsSummaryProvider($repo))->summarize('2025-2026');

        // Header counts measurements (3 in 2026), indicators (2) and breaches (1).
        self::assertStringContainsString('Mediciones de indicadores en 2026: 3 sobre 2 indicador(es), de las cuales 1 superaron el valor de referencia.', $summary);
        // Each measured indicator is named (what is being measured), not just counted.
        self::assertStringContainsString('- Consumo eléctrico por estudiante (referencia ≤ 30 kWh/estudiante): 2 medición(es), 1 fuera de referencia.', $summary);
        self::assertStringContainsString('- Consumo de agua (referencia —): 1 medición(es), 0 fuera de referencia.', $summary);
        // The breach detail trims the DECIMAL trailing zeros ("33.000" -> "33").
        self::assertStringContainsString('- Consumo eléctrico por estudiante (mes 1): valor 33 (referencia ≤ 30 kWh/estudiante)', $summary);
    }

    public function testReturnsEmptyWhenNothingMeasuredInTheYear(): void
    {
        $repo = $this->createMock(IndicatorRepository::class);
        $repo->method('findAllWithMeasurements')->willReturn([
            $this->indicator('Consumo de agua', null, [[2025, 1, '100.000', false]]),
        ]);

        self::assertSame('', (new IndicatorsSummaryProvider($repo))->summarize('2025-2026'));
    }
}
