<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\ConsumptionReading;
use App\Enum\ConsumptionType;
use Doctrine\Persistence\ObjectManager;

/**
 * Monthly consumption readings (electricity, water, gasoil, paper, toner). Sample DEMO data,
 * modelled on the centre's "CONSUMOS LUZ AGUA GASOIL PAPEL TÓNER" sheet: realistic seasonal shapes
 * for a school (heating peaks in winter, near-zero in August).
 *
 * Seeded across three years so the year view defaults to a populated current year and the trend
 * is visible: a base 2025 profile scaled per year ({@see YEAR_FACTORS}, a slight downward trend in
 * line with the reduction objectives). The current year is only filled up to {@see CURRENT_MONTH}.
 */
final class ConsumptionReadingFixtures extends AbstractDemoFixture
{
    /** Year => scaling factor applied to the 2025 base profile. */
    private const array YEAR_FACTORS = [2024 => 1.08, 2025 => 1.0, 2026 => 0.93];

    /** Months already elapsed in the current year (data is partial beyond this). */
    private const int CURRENT_YEAR = 2026;
    private const int CURRENT_MONTH = 6;

    public function load(ObjectManager $manager): void
    {
        // type => [unit price for the synthetic cost, [month => base quantity (2025 profile)]]
        $series = [
            [ConsumptionType::ELECTRICITY, 0.19, [
                1 => 14200, 2 => 13100, 3 => 11800, 4 => 9700, 5 => 9200, 6 => 10100,
                7 => 7300, 8 => 5100, 9 => 9800, 10 => 10600, 11 => 12400, 12 => 13800,
            ]],
            [ConsumptionType::WATER, 2.10, [
                1 => 180, 2 => 175, 3 => 210, 4 => 230, 5 => 245, 6 => 260,
                7 => 90, 8 => 60, 9 => 240, 10 => 250, 11 => 220, 12 => 190,
            ]],
            [ConsumptionType::GASOIL, 1.05, [
                1 => 3200, 2 => 3000, 3 => 2400, 4 => 1500, 10 => 1800, 11 => 2600, 12 => 3100,
            ]],
            [ConsumptionType::PAPER, 4.50, [3 => 120, 6 => 90, 9 => 150, 12 => 110]],
            [ConsumptionType::TONER, 48.00, [3 => 8, 6 => 6, 9 => 10, 12 => 7]],
        ];

        foreach (self::YEAR_FACTORS as $year => $factor) {
            foreach ($series as [$type, $unitPrice, $months]) {
                foreach ($months as $month => $baseQuantity) {
                    if (self::CURRENT_YEAR === $year && $month > self::CURRENT_MONTH) {
                        continue; // current year not finished yet
                    }
                    $quantity = round($baseQuantity * $factor, 3);
                    $reading = new ConsumptionReading();
                    $reading->setType($type)
                        ->setPeriodYear($year)
                        ->setPeriodMonth($month)
                        ->setQuantity((string) $quantity)
                        ->setCost((string) round($quantity * $unitPrice, 2));
                    $manager->persist($reading);
                }
            }
        }

        $manager->flush();
    }
}
