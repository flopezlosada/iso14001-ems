<?php

declare(strict_types=1);

namespace App\Tests\Service\Import;

use App\Enum\AspectType;
use App\Enum\DirectAspectCategory;
use App\Enum\InfluenceLevel;
use App\Enum\ScoreLevel;
use App\Repository\EnvironmentalAspectRepository;
use App\Service\Import\AspectImporter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration tests for the aspects importer over a real test database (rolled back per test).
 * Covers creating each aspect kind with its evaluation, the computed significance (recomputed by
 * the calculator, not read from the sheet), catalogue-only rows with no evaluation, the explicit
 * "Nula" influence level (0), idempotent re-import (no duplicate, no unique clash) and rejection
 * of an unknown type.
 */
final class AspectImporterTest extends KernelTestCase
{
    private AspectImporter $importer;
    private EnvironmentalAspectRepository $aspects;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->importer = $container->get(AspectImporter::class);
        $this->aspects = $container->get(EnvironmentalAspectRepository::class);
        $this->entityManager = $container->get(EntityManagerInterface::class);
    }

    /**
     * @param array<string, string> $overrides
     *
     * @return array<string, string>
     */
    private function row(array $overrides = []): array
    {
        return array_merge([
            'type' => 'direct',
            'category' => 'consumption',
            'name' => 'Electricidad',
            'unit' => 'KWh',
            'associated_impact' => 'Agotamiento recurso natural',
            'year' => '2026',
            'frequency' => '4',
            'intensity' => '2',
            'hazard' => '2',
            'probability' => '',
            'control' => '',
            'severity' => '',
            'influence' => '',
            'notes' => '',
        ], $overrides);
    }

    public function testCreatesDirectAspectWithEvaluationAndSignificance(): void
    {
        // Toner-Tinta (consumos): 6 + 2 + 6 = 14 > umbral consumos (12) -> significativo.
        $report = $this->importer->import([
            $this->row(['name' => 'Toner - Tinta', 'frequency' => '6', 'intensity' => '2', 'hazard' => '6']),
        ], false);
        $this->entityManager->clear();

        self::assertSame(1, $report->getCreated());

        $aspect = $this->aspects->findOneBy(['name' => 'Toner - Tinta']);
        self::assertNotNull($aspect);
        self::assertSame(AspectType::DIRECT, $aspect->getType());
        self::assertSame(DirectAspectCategory::CONSUMPTION, $aspect->getCategory());

        $evaluation = $aspect->getLatestEvaluation();
        self::assertNotNull($evaluation);
        self::assertSame(2026, $evaluation->getYear());
        self::assertSame(ScoreLevel::HIGH, $evaluation->getFrequency());
        self::assertSame(14, $evaluation->getSignificanceScore());
        self::assertTrue($evaluation->isSignificant());
    }

    /**
     * Realistic boundary case from the real register: "Restos de tóner" (residuos) scores 2 + 4 + 4
     * = 10 = waste threshold (10), which the certified sheet marks SIGNIFICATIVO. The app now uses
     * the inclusive ">=" boundary, so the recomputed flag matches the sheet (it was a false negative
     * before, when the rule was a strict ">").
     */
    public function testWasteBoundaryAtThresholdIsSignificant(): void
    {
        $this->importer->import([
            $this->row([
                'name' => 'Restos de tóner', 'category' => 'waste', 'unit' => 'Kg',
                'frequency' => '2', 'intensity' => '4', 'hazard' => '4',
            ]),
        ], false);
        $this->entityManager->clear();

        $evaluation = $this->aspects->findOneBy(['name' => 'Restos de tóner'])?->getLatestEvaluation();
        self::assertNotNull($evaluation);
        self::assertSame(10, $evaluation->getSignificanceScore());
        self::assertTrue($evaluation->isSignificant(), 'La app usa >= sobre el umbral de residuos (10).');
    }

    public function testDischargeIncludesIntensityInScore(): void
    {
        // Vertidos: RG-06.01.01 Rev 02 añadió intensidad -> la suma incluye los tres criterios: 2 + 2 + 2 = 6.
        $this->importer->import([
            $this->row([
                'name' => 'Vertidos a la red municipal', 'category' => 'discharge', 'unit' => 'Litros',
                'frequency' => '2', 'intensity' => '2', 'hazard' => '2',
            ]),
        ], false);
        $this->entityManager->clear();

        $evaluation = $this->aspects->findOneBy(['name' => 'Vertidos a la red municipal'])?->getLatestEvaluation();
        self::assertNotNull($evaluation);
        self::assertSame(6, $evaluation->getSignificanceScore());
    }

    public function testCatalogueOnlyRowCreatesAspectWithoutEvaluation(): void
    {
        // "Aerosoles": en la hoja no tiene valoración (celdas vacías) -> aspecto sin evaluación.
        $this->importer->import([
            $this->row(['name' => 'Aerosoles', 'category' => 'waste', 'frequency' => '', 'intensity' => '', 'hazard' => '']),
        ], false);
        $this->entityManager->clear();

        $aspect = $this->aspects->findOneBy(['name' => 'Aerosoles']);
        self::assertNotNull($aspect);
        self::assertCount(0, $aspect->getEvaluations());
    }

    public function testAllZeroDirectRowIsNotEvaluated(): void
    {
        // "Residuo sanitario" trae 0/0/0: ninguno es un nivel real (2/4/6) -> sin evaluación.
        $this->importer->import([
            $this->row(['name' => 'Residuo sanitario', 'category' => 'waste', 'frequency' => '0', 'intensity' => '0', 'hazard' => '0']),
        ], false);
        $this->entityManager->clear();

        $aspect = $this->aspects->findOneBy(['name' => 'Residuo sanitario']);
        self::assertNotNull($aspect);
        self::assertCount(0, $aspect->getEvaluations());
    }

    public function testIndirectNulaInfluenceMapsToNoneLevelWithZeroScore(): void
    {
        // Capacidad de influencia "Nula" (0) ahora es un nivel explícito del enum (NONE), no null; score 0.
        $this->importer->import([
            $this->row([
                'type' => 'indirect', 'category' => '', 'name' => 'Consumo de combustible trabajadores',
                'unit' => '', 'frequency' => '', 'intensity' => '', 'hazard' => '', 'influence' => '0',
            ]),
        ], false);
        $this->entityManager->clear();

        $evaluation = $this->aspects->findOneBy(['name' => 'Consumo de combustible trabajadores'])?->getLatestEvaluation();
        self::assertNotNull($evaluation);
        self::assertSame(InfluenceLevel::NONE, $evaluation->getInfluence());
        self::assertSame(0, $evaluation->getSignificanceScore());
    }

    public function testIndirectScoredInfluenceMapsToLevel(): void
    {
        $this->importer->import([
            $this->row([
                'type' => 'indirect', 'category' => '', 'name' => 'Generación de residuos RAEE',
                'unit' => '', 'frequency' => '', 'intensity' => '', 'hazard' => '', 'influence' => '1',
            ]),
        ], false);
        $this->entityManager->clear();

        $evaluation = $this->aspects->findOneBy(['name' => 'Generación de residuos RAEE'])?->getLatestEvaluation();
        self::assertNotNull($evaluation);
        self::assertSame(InfluenceLevel::LOW, $evaluation->getInfluence());
        self::assertSame(1, $evaluation->getSignificanceScore());
    }

    public function testAbnormalAspectComputesSignificance(): void
    {
        // Incendio: 2 + 2 + 4 = 8 < umbral anormal (10) -> no significativo.
        $this->importer->import([
            $this->row([
                'type' => 'abnormal', 'category' => '', 'name' => 'Incendio', 'unit' => 'Nº de accidentes',
                'frequency' => '', 'intensity' => '', 'hazard' => '',
                'probability' => '2', 'control' => '2', 'severity' => '4',
            ]),
        ], false);
        $this->entityManager->clear();

        $evaluation = $this->aspects->findOneBy(['name' => 'Incendio'])?->getLatestEvaluation();
        self::assertNotNull($evaluation);
        self::assertSame(8, $evaluation->getSignificanceScore());
        self::assertFalse($evaluation->isSignificant());
    }

    public function testReimportIsIdempotentAndUpdatesInPlace(): void
    {
        $this->importer->import([$this->row(['name' => 'Agua', 'category' => 'consumption'])], false);
        $this->entityManager->clear();

        // Misma clave (nombre + año), distinta valoración: actualiza en sitio, sin choque de único.
        $report = $this->importer->import([
            $this->row(['name' => 'Agua', 'category' => 'consumption', 'frequency' => '6', 'intensity' => '6', 'hazard' => '6']),
        ], false);
        $this->entityManager->clear();

        self::assertSame(0, $report->getCreated());
        self::assertSame(1, $report->getUpdated());

        $found = $this->aspects->findBy(['name' => 'Agua']);
        self::assertCount(1, $found, 'No debe duplicar el aspecto.');
        self::assertCount(1, $found[0]->getEvaluations(), 'No debe duplicar la evaluación del año.');
        self::assertSame(18, $found[0]->getLatestEvaluation()?->getSignificanceScore());
    }

    public function testRejectsUnknownTypeWithoutPersisting(): void
    {
        $report = $this->importer->import([$this->row(['type' => 'mythical', 'name' => 'Magia'])], false);

        self::assertSame(0, $report->getProcessed());
        self::assertCount(1, $report->getRejected());
        self::assertNull($this->aspects->findOneBy(['name' => 'Magia']));
    }

    public function testRejectsInvalidYearWithoutPersisting(): void
    {
        // El año es la clave del unique (aspect, year); una celda vacía -> 0, fuera de rango.
        $report = $this->importer->import([$this->row(['name' => 'Sin año', 'year' => ''])], false);

        self::assertSame(0, $report->getProcessed());
        self::assertCount(1, $report->getRejected());
        self::assertNull($this->aspects->findOneBy(['name' => 'Sin año']));
    }
}
