<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\AspectEvaluation;
use App\Entity\EnvironmentalAspect;
use App\Enum\AspectType;
use App\Enum\ConsumptionType;
use App\Enum\DirectAspectCategory;
use App\Enum\InfluenceLevel;
use App\Enum\ScoreLevel;
use App\Service\AspectSignificanceCalculator;
use Doctrine\Persistence\ObjectManager;

/**
 * The catalog of environmental aspects and their yearly evaluations (PG-06.01). DEMO layer only:
 * the real catalog and evaluations are loaded by the ETL ('aspects' importer), so seeding these
 * here as part of the GOLDEN backbone would duplicate them against the real data.
 *
 * Synthetic but realistic for a secondary school (electricity, hazardous waste, boiler emissions,
 * waste-water discharge, an indirect aspect, an abnormal/accidental one). The significance score
 * and flag are NOT hand-written: they are computed by the real {@see AspectSignificanceCalculator}
 * so the seeded data always satisfies the same invariant the application enforces.
 */
final class EnvironmentalAspectFixtures extends AbstractDemoFixture
{
    public function __construct(private readonly AspectSignificanceCalculator $calculator)
    {
    }

    /** Reference name for the aspect with the given key, so objectives can wire to it. */
    public static function ref(string $key): string
    {
        return 'aspect-'.$key;
    }

    public function load(ObjectManager $manager): void
    {
        foreach ($this->definitions() as $key => $def) {
            $aspect = new EnvironmentalAspect();
            $aspect->setName($def['name'])
                ->setType($def['type'])
                ->setCategory($def['category'] ?? null)
                ->setUnit($def['unit'] ?? null)
                ->setLinkedConsumptionType($def['linkedConsumptionType'] ?? null)
                ->setLinkedLerCodes($def['linkedLerCodes'] ?? [])
                ->setAssociatedImpact($def['impact'] ?? null)
                ->setActive(true);

            // Evaluate the two most recent years so the significance trend is visible.
            foreach ($def['scores'] as $year => $scores) {
                $evaluation = $this->buildEvaluation($aspect, $year, $scores);
                $aspect->addEvaluation($evaluation);
            }

            $manager->persist($aspect);
            $this->addReference(self::ref($key), $aspect);
        }

        $manager->flush();
    }

    /**
     * Builds and scores one evaluation. The criterion levels relevant to the aspect type are set,
     * then the real calculator fills the score and significance flag.
     *
     * @param array<string, ScoreLevel|InfluenceLevel> $scores criterion level by setter suffix
     */
    private function buildEvaluation(EnvironmentalAspect $aspect, int $year, array $scores): AspectEvaluation
    {
        $evaluation = new AspectEvaluation();
        $evaluation->setAspect($aspect)->setYear($year);

        $evaluation->setFrequency($scores['frequency'] ?? null)
            ->setIntensity($scores['intensity'] ?? null)
            ->setHazard($scores['hazard'] ?? null)
            ->setProbability($scores['probability'] ?? null)
            ->setControl($scores['control'] ?? null)
            ->setSeverity($scores['severity'] ?? null)
            ->setInfluence($scores['influence'] ?? null);

        $this->calculator->apply($evaluation);

        return $evaluation;
    }

    /**
     * @return array<string, array{name: string, type: AspectType, category?: DirectAspectCategory,
     *     unit?: string, linkedConsumptionType?: ConsumptionType, linkedLerCodes?: list<string>, impact?: string,
     *     scores: array<int, array<string, ScoreLevel|InfluenceLevel>>}>
     */
    private function definitions(): array
    {
        $hi = ScoreLevel::HIGH;
        $mid = ScoreLevel::MEDIUM;
        $lo = ScoreLevel::LOW;

        return [
            'electricidad' => [
                'name' => 'Consumo de energía eléctrica',
                'type' => AspectType::DIRECT,
                'category' => DirectAspectCategory::CONSUMPTION,
                'unit' => 'kWh',
                'linkedConsumptionType' => ConsumptionType::ELECTRICITY,
                'impact' => 'Agotamiento de recursos naturales',
                'scores' => [
                    2025 => ['frequency' => $hi, 'intensity' => $mid, 'hazard' => $mid],
                    2026 => ['frequency' => $hi, 'intensity' => $hi, 'hazard' => $mid],
                ],
            ],
            'residuos-peligrosos' => [
                'name' => 'Generación de residuos peligrosos (fluorescentes y tóner)',
                'type' => AspectType::DIRECT,
                'category' => DirectAspectCategory::WASTE,
                'unit' => 'kg',
                'linkedLerCodes' => ['200121', '080318'],
                'impact' => 'Contaminación del suelo y agua',
                'scores' => [
                    2025 => ['frequency' => $mid, 'intensity' => $hi, 'hazard' => $hi],
                    2026 => ['frequency' => $mid, 'intensity' => $mid, 'hazard' => $hi],
                ],
            ],
            'emisiones-caldera' => [
                'name' => 'Emisiones de la caldera de calefacción',
                'type' => AspectType::DIRECT,
                'category' => DirectAspectCategory::EMISSION,
                'unit' => 't CO₂eq',
                'impact' => 'Contribución al cambio climático',
                'scores' => [
                    2025 => ['frequency' => $hi, 'intensity' => $mid, 'hazard' => $mid],
                    2026 => ['frequency' => $mid, 'intensity' => $mid, 'hazard' => $mid],
                ],
            ],
            'vertido-aguas' => [
                'name' => 'Vertido de aguas residuales a la red de saneamiento',
                'type' => AspectType::DIRECT,
                'category' => DirectAspectCategory::DISCHARGE,
                'unit' => 'm³',
                'impact' => 'Contaminación del agua',
                // Discharges now carry intensity too (RG-06.01.01 Rev 02): freq + intensity + hazard.
                'scores' => [
                    2025 => ['frequency' => $lo, 'intensity' => $lo, 'hazard' => $lo],
                    2026 => ['frequency' => $lo, 'intensity' => $lo, 'hazard' => $lo],
                ],
            ],
            'concienciacion-alumnado' => [
                'name' => 'Concienciación ambiental del alumnado',
                'type' => AspectType::INDIRECT,
                'impact' => 'Mejora del comportamiento ambiental',
                // Indirect aspects only record the capacity of influence; significance stays manual.
                'scores' => [
                    2025 => ['influence' => InfluenceLevel::MEDIUM],
                    2026 => ['influence' => InfluenceLevel::HIGH],
                ],
            ],
            'derrame-limpieza' => [
                'name' => 'Derrame accidental de productos de limpieza',
                'type' => AspectType::ABNORMAL,
                'impact' => 'Contaminación del suelo',
                'scores' => [
                    2025 => ['probability' => $lo, 'control' => $mid, 'severity' => $hi],
                    2026 => ['probability' => $lo, 'control' => $lo, 'severity' => $hi],
                ],
            ],
        ];
    }
}
