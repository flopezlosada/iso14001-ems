<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Indicator;
use App\Entity\IndicatorMeasurement;
use App\Enum\MeasurementPeriodicity;
use App\Enum\SgmaProcess;
use Doctrine\Persistence\ObjectManager;

/**
 * Performance indicators of the management system and their historical measurements (F.09.0).
 * Sample DEMO data, modelled on the centre's "INDICADORES" sheet.
 *
 * Monthly indicators carry the full 2025 year and the elapsed months of 2026, so the indicator
 * views are populated in the current year. A few measurements are flagged as breached to exercise
 * the "open a non-conformity" link on the detail view.
 */
final class IndicatorFixtures extends AbstractDemoFixture
{
    private const int CURRENT_YEAR = 2026;
    private const int CURRENT_MONTH = 6;

    public function load(ObjectManager $manager): void
    {
        foreach ($this->definitions() as $def) {
            $indicator = new Indicator();
            $indicator->setName($def['name'])
                ->setMeasurementDescription($def['description'])
                ->setReferenceValue($def['reference'])
                ->setProcess($def['process'])
                ->setPeriodicity($def['periodicity'])
                ->setActive(true);

            foreach ($def['measurements'] as [$year, $month, $value, $breached]) {
                $measurement = new IndicatorMeasurement();
                $measurement->setYear($year)
                    ->setMonth($month)
                    ->setValue((string) $value)
                    ->setBreached($breached);
                $indicator->addMeasurement($measurement);
            }

            $manager->persist($indicator);
        }

        $manager->flush();
    }

    /**
     * @return list<array{name: string, description: string, reference: string, process: SgmaProcess,
     *     periodicity: MeasurementPeriodicity, measurements: list<array{0: int, 1: int, 2: float|int, 3: bool}>}>
     */
    private function definitions(): array
    {
        return [
            [
                'name' => 'Consumo eléctrico por alumno',
                'description' => 'kWh facturados en el mes / nº de alumnos matriculados.',
                'reference' => '≤ 30 kWh/alumno',
                'process' => SgmaProcess::PERFORMANCE_EVALUATION,
                'periodicity' => MeasurementPeriodicity::MONTHLY,
                // breached when above the 30 kWh/alumno threshold.
                'measurements' => $this->monthly([
                    2025 => [1 => 33.2, 2 => 31.5, 3 => 28.4, 4 => 24.1, 5 => 22.8, 6 => 25.0, 7 => 18.2, 8 => 12.7, 9 => 24.5, 10 => 26.3, 11 => 30.8, 12 => 34.1],
                    2026 => [1 => 30.9, 2 => 29.2, 3 => 26.1, 4 => 22.4, 5 => 21.2, 6 => 23.3],
                ], 30.0),
            ],
            [
                'name' => 'Consumo de agua',
                'description' => 'm³ consumidos según factura del mes.',
                'reference' => 'Reducción del 5% anual',
                'process' => SgmaProcess::PERFORMANCE_EVALUATION,
                'periodicity' => MeasurementPeriodicity::MONTHLY,
                'measurements' => $this->monthly([
                    2025 => [1 => 180, 2 => 175, 3 => 210, 4 => 230, 5 => 245, 6 => 260, 7 => 90, 8 => 60, 9 => 240, 10 => 250, 11 => 220, 12 => 190],
                    2026 => [1 => 168, 2 => 163, 3 => 195, 4 => 214, 5 => 228, 6 => 242],
                ], null),
            ],
            [
                'name' => 'Generación de residuos peligrosos',
                'description' => 'kg de residuos peligrosos retirados en el trimestre.',
                'reference' => '≤ 50 kg/trimestre',
                'process' => SgmaProcess::IMPROVEMENT,
                'periodicity' => MeasurementPeriodicity::QUARTERLY,
                'measurements' => [
                    [2025, 3, 31.0, false], [2025, 6, 24.5, false], [2025, 9, 18.0, false], [2025, 12, 52.5, true],
                    [2026, 3, 22.5, false], [2026, 6, 19.0, false],
                ],
            ],
            [
                'name' => 'Nº de no conformidades abiertas',
                'description' => 'Recuento de no conformidades sin cerrar al final del mes.',
                'reference' => '0',
                'process' => SgmaProcess::IMPROVEMENT,
                'periodicity' => MeasurementPeriodicity::MONTHLY,
                // breached whenever there is at least one open non-conformity.
                'measurements' => $this->monthly([
                    2025 => [3 => 1, 4 => 1, 5 => 1, 6 => 0, 9 => 0, 12 => 0],
                    2026 => [1 => 1, 2 => 1, 3 => 1, 4 => 1, 5 => 2, 6 => 2],
                ], 0.0),
            ],
            [
                'name' => 'Cumplimiento del plan de formación ambiental',
                'description' => 'Acciones formativas realizadas / planificadas × 100.',
                'reference' => '100%',
                'process' => SgmaProcess::RESOURCES,
                'periodicity' => MeasurementPeriodicity::ANNUAL,
                'measurements' => [
                    [2025, 12, 100, false],
                ],
            ],
        ];
    }

    /**
     * Builds monthly measurements from per-year value maps, flagging a breach when a threshold is
     * given and the value exceeds it. The current year is truncated to the elapsed months.
     *
     * @param array<int, array<int, float|int>> $valuesByYear year => [month => value]
     *
     * @return list<array{0: int, 1: int, 2: float|int, 3: bool}>
     */
    private function monthly(array $valuesByYear, ?float $threshold): array
    {
        $measurements = [];
        foreach ($valuesByYear as $year => $months) {
            foreach ($months as $month => $value) {
                if (self::CURRENT_YEAR === $year && $month > self::CURRENT_MONTH) {
                    continue;
                }
                $breached = null !== $threshold && $value > $threshold;
                $measurements[] = [$year, $month, $value, $breached];
            }
        }

        return $measurements;
    }
}
