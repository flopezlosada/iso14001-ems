<?php

declare(strict_types=1);

namespace App\Tests\Service\Import;

use App\Repository\WasteRecordRepository;
use App\Service\Import\WasteImporter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration tests for the waste-register importer over a real test database (rolled back per
 * test). Covers the clean path, the messy real shapes preserved as null + notes (illegible LER,
 * non-kg amount, free-text date), the hazardous flag and idempotent re-import by source hash.
 */
final class WasteImporterTest extends KernelTestCase
{
    private WasteImporter $importer;
    private WasteRecordRepository $records;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->importer = $container->get(WasteImporter::class);
        $this->records = $container->get(WasteRecordRepository::class);
        $this->entityManager = $container->get(EntityManagerInterface::class);
    }

    /**
     * @param array<string, string> $overrides
     *
     * @return array<string, string>
     */
    private function row(array $overrides = []): array
    {
        return [
            'ler_code' => '160214',
            'description' => 'Aparatos pequeños',
            'quantity_kg' => '25',
            'pickup_date' => '2023-02-01',
            'manager' => 'GESTOR DEMO SL',
            'hazardous' => '0',
            'notes' => '',
        ] + $overrides;
    }

    public function testImportsCleanRow(): void
    {
        $report = $this->importer->import([$this->row()], false);
        $this->entityManager->clear();

        self::assertSame(1, $report->getCreated());
        self::assertSame([], $report->getRejected());

        $record = $this->records->findOneBy(['lerCode' => '160214']);
        self::assertNotNull($record);
        self::assertSame('25.000', $record->getQuantityKg());
        self::assertSame('2023-02-01', $record->getPickupDate()?->format('Y-m-d'));
    }

    public function testPreservesMessyRowAsNullWithNotes(): void
    {
        // Illegible LER, non-kg amount and free-text date: kept as a record with nulls + notes.
        $report = $this->importer->import([$this->row([
            'ler_code' => '',
            'description' => 'Restos de poda',
            'quantity_kg' => '',
            'pickup_date' => '',
            'manager' => '',
            'notes' => 'código LER original ilegible: 36942.0 | cantidad original: 43 bolsones',
        ])], false);

        self::assertSame(1, $report->getCreated());
        self::assertSame([], $report->getRejected());

        $record = $this->records->findOneBy(['description' => 'Restos de poda']);
        self::assertNotNull($record);
        self::assertNull($record->getLerCode());
        self::assertNull($record->getQuantityKg());
        self::assertNull($record->getPickupDate());
        self::assertNull($record->getManager());
        self::assertStringContainsString('43 bolsones', (string) $record->getNotes());
    }

    public function testHazardousFlagIsImported(): void
    {
        $this->importer->import([$this->row(['hazardous' => '1', 'description' => 'Fluorescentes'])], false);
        $this->entityManager->clear();

        $record = $this->records->findOneBy(['description' => 'Fluorescentes']);
        self::assertNotNull($record);
        self::assertTrue($record->isHazardous());
    }

    public function testReimportIsIdempotentBySourceHash(): void
    {
        $this->importer->import([$this->row()], false);
        $this->entityManager->clear();

        $report = $this->importer->import([$this->row()], false);

        self::assertSame(0, $report->getCreated());
        self::assertSame(1, $report->getUpdated());
        self::assertCount(1, $this->records->findBy(['lerCode' => '160214']));
    }

    public function testEditedRowIsImportedAsNewRecord(): void
    {
        $this->importer->import([$this->row()], false);
        $this->entityManager->clear();

        // A different amount changes the hash → a distinct record (the register has no natural key).
        $report = $this->importer->import([$this->row(['quantity_kg' => '30'])], false);

        self::assertSame(1, $report->getCreated());
        self::assertCount(2, $this->records->findBy(['lerCode' => '160214']));
    }

    public function testRejectsMalformedDateWithoutPersisting(): void
    {
        $report = $this->importer->import([$this->row(['pickup_date' => 'Junio 2024'])], false);

        self::assertSame(0, $report->getProcessed());
        self::assertCount(1, $report->getRejected());
        self::assertNull($this->records->findOneBy(['lerCode' => '160214']));
    }

    public function testRejectsBlankDescriptionWithoutPersisting(): void
    {
        // description is the one field that stays required; a blank one must be quarantined.
        $report = $this->importer->import([$this->row(['description' => '   '])], false);

        self::assertSame(0, $report->getProcessed());
        self::assertCount(1, $report->getRejected());
        self::assertNull($this->records->findOneBy(['lerCode' => '160214']));
    }
}
