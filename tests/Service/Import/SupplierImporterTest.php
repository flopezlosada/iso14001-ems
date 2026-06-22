<?php

declare(strict_types=1);

namespace App\Tests\Service\Import;

use App\Enum\SupplierCriterion;
use App\Repository\SupplierRepository;
use App\Service\Import\SupplierImporter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration tests for the supplier importer over a real test database (rolled back per test).
 * Covers creating a supplier with a yearly evaluation, idempotent re-import (no duplicate supplier
 * or evaluation, no unique-key clash), updating the criterion and rejecting an unknown criterion.
 */
final class SupplierImporterTest extends KernelTestCase
{
    private SupplierImporter $importer;
    private SupplierRepository $suppliers;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->importer = $container->get(SupplierImporter::class);
        $this->suppliers = $container->get(SupplierRepository::class);
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
            'supplier_name' => 'Dismalibro S.L',
            'product_or_service' => 'Libros',
            'year' => '2024',
            'criterion' => 'capable',
        ], $overrides);
    }

    public function testCreatesSupplierWithYearlyEvaluation(): void
    {
        $report = $this->importer->import([$this->row()], false);
        $this->entityManager->clear();

        self::assertSame(1, $report->getCreated());

        $supplier = $this->suppliers->findOneBy(['name' => 'Dismalibro S.L']);
        self::assertNotNull($supplier);
        self::assertCount(1, $supplier->getEvaluations());
        $evaluation = $supplier->getEvaluations()->first();
        self::assertNotFalse($evaluation);
        self::assertSame(2024, $evaluation->getYear());
        self::assertSame(SupplierCriterion::CAPABLE, $evaluation->getCriterion());
    }

    public function testImportsSeveralYearsForOneSupplier(): void
    {
        $this->importer->import([
            $this->row(['year' => '2024']),
            $this->row(['year' => '2025']),
            $this->row(['year' => '2026']),
        ], false);
        $this->entityManager->clear();

        $supplier = $this->suppliers->findOneBy(['name' => 'Dismalibro S.L']);
        self::assertNotNull($supplier);
        self::assertCount(3, $supplier->getEvaluations(), 'Una evaluación por año, un solo proveedor.');
    }

    public function testReimportIsIdempotentAndUpdatesCriterion(): void
    {
        $this->importer->import([$this->row()], false);
        $this->entityManager->clear();

        // Same supplier and year, different criterion: must update in place, not clash with the
        // unique (supplier, year) nor duplicate the supplier.
        $report = $this->importer->import([$this->row(['criterion' => 'on_trial'])], false);
        $this->entityManager->clear();

        self::assertSame(0, $report->getCreated());
        self::assertSame(1, $report->getUpdated());

        $found = $this->suppliers->findBy(['name' => 'Dismalibro S.L']);
        self::assertCount(1, $found, 'No debe duplicar el proveedor.');
        self::assertCount(1, $found[0]->getEvaluations(), 'No debe duplicar la evaluación del año.');
        $evaluation = $found[0]->getEvaluations()->first();
        self::assertNotFalse($evaluation);
        self::assertSame(SupplierCriterion::ON_TRIAL, $evaluation->getCriterion());
    }

    public function testRejectsUnknownCriterionWithoutPersisting(): void
    {
        $report = $this->importer->import([$this->row(['criterion' => 'maybe'])], false);

        self::assertSame(0, $report->getProcessed());
        self::assertCount(1, $report->getRejected());
        self::assertNull($this->suppliers->findOneBy(['name' => 'Dismalibro S.L']));
    }
}
