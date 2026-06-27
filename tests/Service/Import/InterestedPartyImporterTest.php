<?php

declare(strict_types=1);

namespace App\Tests\Service\Import;

use App\Repository\InterestedPartyRepository;
use App\Service\Import\InterestedPartyImporter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration tests for the interested parties importer over a real test database (rolled back per
 * test). Covers creating a party, importing several years, idempotent re-import (update in place by
 * (year, name), no duplicate), and rejecting a row with blank needs/expectations.
 */
final class InterestedPartyImporterTest extends KernelTestCase
{
    private InterestedPartyImporter $importer;
    private InterestedPartyRepository $parties;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->importer = $container->get(InterestedPartyImporter::class);
        $this->parties = $container->get(InterestedPartyRepository::class);
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
            'review_year' => '2025',
            'name' => 'Usuarios/Alumnos',
            'needs_and_expectations' => "Atención personalizada.\nConfidencialidad.",
            'incidents' => 'NO',
        ], $overrides);
    }

    public function testCreatesPartyWithAllColumns(): void
    {
        $report = $this->importer->import([$this->row()], false);
        $this->entityManager->clear();

        self::assertSame(1, $report->getCreated());

        $party = $this->parties->findOneBy(['reviewYear' => 2025, 'name' => 'Usuarios/Alumnos']);
        self::assertNotNull($party);
        self::assertStringContainsString('Confidencialidad.', $party->getNeedsAndExpectations());
        self::assertSame('NO', $party->getIncidents());
    }

    public function testImportsSeveralYearsOfTheSameParty(): void
    {
        $this->importer->import([
            $this->row(['review_year' => '2023']),
            $this->row(['review_year' => '2024']),
            $this->row(['review_year' => '2025']),
        ], false);
        $this->entityManager->clear();

        // Same name across three years: three distinct rows, one per review year.
        self::assertCount(1, $this->parties->findForYear(2023));
        self::assertCount(1, $this->parties->findForYear(2024));
        self::assertCount(1, $this->parties->findForYear(2025));
    }

    public function testReimportIsIdempotentAndUpdatesInPlace(): void
    {
        $this->importer->import([$this->row()], false);
        $this->entityManager->clear();

        // Same (year, name), different needs: must update in place, not duplicate the party.
        $report = $this->importer->import([$this->row(['needs_and_expectations' => 'Necesidades revisadas.'])], false);
        $this->entityManager->clear();

        self::assertSame(0, $report->getCreated());
        self::assertSame(1, $report->getUpdated());

        $parties = $this->parties->findForYear(2025);
        self::assertCount(1, $parties);
        self::assertSame('Necesidades revisadas.', $parties[0]->getNeedsAndExpectations());
    }

    public function testSamePartyTwiceInOneRunMergesInsteadOfDuplicating(): void
    {
        // Two rows with the same (year, name) in a single run: the in-call $seen cache must update
        // the same party (not persist it twice nor clash), so the second row wins.
        $report = $this->importer->import([
            $this->row(['needs_and_expectations' => 'Primera necesidad.']),
            $this->row(['needs_and_expectations' => 'Necesidades revisadas.']),
        ], false);
        $this->entityManager->clear();

        self::assertSame(1, $report->getCreated());
        self::assertSame(1, $report->getUpdated());

        $parties = $this->parties->findForYear(2025);
        self::assertCount(1, $parties);
        self::assertSame('Necesidades revisadas.', $parties[0]->getNeedsAndExpectations());
    }

    public function testDryRunDoesNotPersist(): void
    {
        $report = $this->importer->import([$this->row()], true);
        $this->entityManager->clear();

        self::assertSame(1, $report->getCreated());
        self::assertCount(0, $this->parties->findForYear(2025));
    }

    public function testRejectsRowWithBlankNeeds(): void
    {
        // Realistic dirty shape: a party with a name but no needs/expectations (NOT NULL column).
        $report = $this->importer->import([$this->row(['needs_and_expectations' => ''])], false);
        $this->entityManager->clear();

        self::assertSame(0, $report->getCreated());
        self::assertCount(1, $report->getRejected());
        self::assertCount(0, $this->parties->findForYear(2025));
    }

    public function testBlankIncidentsAreStoredAsNull(): void
    {
        $this->importer->import([$this->row(['incidents' => ''])], false);
        $this->entityManager->clear();

        $party = $this->parties->findOneBy(['reviewYear' => 2025, 'name' => 'Usuarios/Alumnos']);
        self::assertNotNull($party);
        self::assertNull($party->getIncidents());
    }
}
