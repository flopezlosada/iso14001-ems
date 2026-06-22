<?php

declare(strict_types=1);

namespace App\Tests\Service\Import;

use App\Enum\ConsumptionType;
use App\Repository\ConsumptionReadingRepository;
use App\Service\Import\ConsumptionImporter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration tests for the consumptions importer, exercised against the real validator (including
 * the entity's UniqueEntity constraint) and a real test database (rolled back per test by DAMA).
 * Covers the happy path, idempotent re-import (upsert by natural key), the toner cost rule and the
 * rejection of invalid rows without persisting them.
 */
final class ConsumptionImporterTest extends KernelTestCase
{
    private ConsumptionImporter $importer;
    private ConsumptionReadingRepository $readings;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->importer = $container->get(ConsumptionImporter::class);
        $this->readings = $container->get(ConsumptionReadingRepository::class);
        $this->entityManager = $container->get(EntityManagerInterface::class);
    }

    /**
     * Builds a single normalized CSV row with sensible defaults.
     *
     * @param array<string, string> $overrides
     *
     * @return array<string, string>
     */
    private function row(array $overrides = []): array
    {
        return [
            'type' => 'electricity',
            'period_year' => '2024',
            'period_month' => '3',
            'quantity' => '4380',
            'cost' => '1543.66',
            'notes' => '',
        ] + $overrides;
    }

    public function testCreatesReadingFromRow(): void
    {
        $report = $this->importer->import([$this->row()], false);
        $this->entityManager->clear(); // re-read from DB to assert the persisted, decimal-cast values

        self::assertSame(1, $report->getCreated());
        self::assertSame(0, $report->getUpdated());
        self::assertSame([], $report->getRejected());

        $reading = $this->readings->findOneByPeriod(ConsumptionType::ELECTRICITY, 2024, 3);
        self::assertNotNull($reading);
        self::assertSame('4380.000', $reading->getQuantity());
        self::assertSame('1543.66', $reading->getCost());
    }

    public function testReimportIsIdempotentAndUpdatesInPlace(): void
    {
        $this->importer->import([$this->row()], false);
        $this->entityManager->clear();

        $report = $this->importer->import([$this->row(['quantity' => '5000'])], false);

        self::assertSame(0, $report->getCreated());
        self::assertSame(1, $report->getUpdated());

        $found = $this->readings->findBy(['type' => ConsumptionType::ELECTRICITY, 'periodYear' => 2024, 'periodMonth' => 3]);
        self::assertCount(1, $found, 'No debe duplicar: misma clave natural (tipo, año, mes).');
        self::assertSame('5000.000', $found[0]->getQuantity());
    }

    public function testTonerWithoutCostIsImported(): void
    {
        // The normalizer emits toner with an empty cost; that is the realistic input shape.
        $report = $this->importer->import([$this->row(['type' => 'toner', 'quantity' => '2', 'cost' => ''])], false);

        self::assertSame(1, $report->getCreated());
        self::assertSame([], $report->getRejected());

        $reading = $this->readings->findOneByPeriod(ConsumptionType::TONER, 2024, 3);
        self::assertNotNull($reading);
        self::assertNull($reading->getCost(), 'El tóner no registra coste.');
    }

    public function testTonerWithCostIsRejectedBySafetyNet(): void
    {
        // A toner row that carries a cost is inconsistent; the entity validation is the safety net.
        $report = $this->importer->import([$this->row(['type' => 'toner', 'quantity' => '2', 'cost' => '99.00'])], false);

        self::assertSame(0, $report->getProcessed());
        self::assertCount(1, $report->getRejected());
        self::assertNull($this->readings->findOneByPeriod(ConsumptionType::TONER, 2024, 3));
    }

    public function testRejectsUnknownTypeWithoutPersisting(): void
    {
        $report = $this->importer->import([$this->row(['type' => 'plutonium'])], false);

        self::assertSame(0, $report->getProcessed());
        self::assertCount(1, $report->getRejected());
        self::assertStringContainsString('Tipo de consumo desconocido', $report->getRejected()[0]['reason']);
    }

    public function testRejectsInvalidQuantityWithoutPersisting(): void
    {
        $report = $this->importer->import([$this->row(['quantity' => '-5'])], false);

        self::assertSame(0, $report->getProcessed());
        self::assertCount(1, $report->getRejected());
        self::assertNull($this->readings->findOneByPeriod(ConsumptionType::ELECTRICITY, 2024, 3));
    }

    public function testDryRunDoesNotFlush(): void
    {
        $report = $this->importer->import([$this->row()], true);
        $this->entityManager->clear();

        self::assertSame(1, $report->getCreated());
        self::assertNull($this->readings->findOneByPeriod(ConsumptionType::ELECTRICITY, 2024, 3));
    }
}
