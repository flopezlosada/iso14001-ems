<?php

declare(strict_types=1);

namespace App\Tests\Service\Import;

use App\Enum\MeasurementPeriodicity;
use App\Enum\SgmaProcess;
use App\Repository\IndicatorRepository;
use App\Service\Import\IndicatorImporter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration tests for the indicators importer over a real test database (rolled back per test).
 * Covers creating an indicator from a metadata row, adding monthly measurements, idempotent
 * re-import (no duplicate measurement, no unique clash) and rejection of an unknown process.
 */
final class IndicatorImporterTest extends KernelTestCase
{
    private IndicatorImporter $importer;
    private IndicatorRepository $indicators;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->importer = $container->get(IndicatorImporter::class);
        $this->indicators = $container->get(IndicatorRepository::class);
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
            'name' => 'Consumo de energía',
            'process' => 'performance_evaluation',
            'periodicity' => 'monthly',
            'reference_value' => '5000',
            'measurement_description' => '% consumo de energía',
            'year' => '',
            'month' => '',
            'value' => '',
        ], $overrides);
    }

    public function testCreatesIndicatorFromMetadataRow(): void
    {
        $report = $this->importer->import([$this->row()], false);
        $this->entityManager->clear();

        self::assertSame(1, $report->getCreated());

        $indicator = $this->indicators->findOneBy(['name' => 'Consumo de energía']);
        self::assertNotNull($indicator);
        self::assertSame(SgmaProcess::PERFORMANCE_EVALUATION, $indicator->getProcess());
        self::assertSame(MeasurementPeriodicity::MONTHLY, $indicator->getPeriodicity());
        self::assertCount(0, $indicator->getMeasurements());
    }

    public function testAddsMonthlyMeasurements(): void
    {
        $this->importer->import([
            $this->row(),
            $this->row(['year' => '2023', 'month' => '1', 'value' => '4380']),
            $this->row(['year' => '2023', 'month' => '2', 'value' => '6417']),
        ], false);
        $this->entityManager->clear();

        $indicator = $this->indicators->findOneBy(['name' => 'Consumo de energía']);
        self::assertNotNull($indicator);
        self::assertCount(2, $indicator->getMeasurements());
    }

    public function testReimportIsIdempotentAndUpdatesMeasurement(): void
    {
        $this->importer->import([
            $this->row(),
            $this->row(['year' => '2023', 'month' => '1', 'value' => '4380']),
        ], false);
        $this->entityManager->clear();

        // Same indicator and period, different value: update in place, no unique clash, no dup.
        $report = $this->importer->import([
            $this->row(['year' => '2023', 'month' => '1', 'value' => '4500']),
        ], false);
        $this->entityManager->clear();

        self::assertSame(0, $report->getCreated());
        self::assertSame(1, $report->getUpdated());

        $found = $this->indicators->findBy(['name' => 'Consumo de energía']);
        self::assertCount(1, $found, 'No debe duplicar el indicador.');
        self::assertCount(1, $found[0]->getMeasurements(), 'No debe duplicar la medida del periodo.');
        $measurement = $found[0]->getMeasurements()->first();
        self::assertNotFalse($measurement);
        self::assertSame('4500.000', $measurement->getValue());
    }

    public function testRejectsUnknownProcessWithoutPersisting(): void
    {
        $report = $this->importer->import([$this->row(['process' => 'telepathy'])], false);

        self::assertSame(0, $report->getProcessed());
        self::assertCount(1, $report->getRejected());
        self::assertNull($this->indicators->findOneBy(['name' => 'Consumo de energía']));
    }
}
