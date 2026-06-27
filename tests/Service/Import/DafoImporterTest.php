<?php

declare(strict_types=1);

namespace App\Tests\Service\Import;

use App\Repository\DafoAnalysisRepository;
use App\Service\Import\DafoImporter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration tests for the DAFO importer over a real test database (rolled back per test). Covers
 * creating an analysis with its four quadrants, idempotent re-import (update in place by school
 * year, no duplicate), dry-run, and rejecting a row with an invalid school-year format.
 */
final class DafoImporterTest extends KernelTestCase
{
    private DafoImporter $importer;
    private DafoAnalysisRepository $analyses;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->importer = $container->get(DafoImporter::class);
        $this->analyses = $container->get(DafoAnalysisRepository::class);
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
            'school_year' => '2025-2026',
            'weaknesses' => "Falta de conocimientos ambientales.\nFinanciación dependiente.",
            'threats' => 'Cambios en legislación ambiental.',
            'strengths' => 'Recursos humanos cualificados.',
            'opportunities' => 'Reconocimiento al obtener la certificación.',
        ], $overrides);
    }

    public function testCreatesAnalysisWithFourQuadrants(): void
    {
        $report = $this->importer->import([$this->row()], false);
        $this->entityManager->clear();

        self::assertSame(1, $report->getCreated());

        $analysis = $this->analyses->findOneBy(['schoolYear' => '2025-2026']);
        self::assertNotNull($analysis);
        self::assertStringContainsString('Financiación dependiente.', (string) $analysis->getWeaknesses());
        self::assertSame('Cambios en legislación ambiental.', $analysis->getThreats());
        self::assertSame('Recursos humanos cualificados.', $analysis->getStrengths());
        self::assertSame('Reconocimiento al obtener la certificación.', $analysis->getOpportunities());
    }

    public function testReimportIsIdempotentAndUpdatesInPlace(): void
    {
        $this->importer->import([$this->row()], false);
        $this->entityManager->clear();

        // Same school year, different content: must update in place, not duplicate nor clash with
        // the unique school_year.
        $report = $this->importer->import([$this->row(['strengths' => 'Fortalezas revisadas.'])], false);
        $this->entityManager->clear();

        self::assertSame(0, $report->getCreated());
        self::assertSame(1, $report->getUpdated());

        $all = $this->analyses->findAllOrdered();
        self::assertCount(1, $all);
        self::assertSame('Fortalezas revisadas.', $all[0]->getStrengths());
    }

    public function testSameSchoolYearTwiceInOneRunMergesInsteadOfDuplicating(): void
    {
        // Two rows with the same school year in a single run: the in-call $seen cache must update
        // the same analysis (not persist it twice nor clash with the unique school_year).
        $report = $this->importer->import([
            $this->row(['strengths' => 'Primera versión.']),
            $this->row(['strengths' => 'Versión revisada.']),
        ], false);
        $this->entityManager->clear();

        self::assertSame(1, $report->getCreated());
        self::assertSame(1, $report->getUpdated());

        $all = $this->analyses->findAllOrdered();
        self::assertCount(1, $all);
        self::assertSame('Versión revisada.', $all[0]->getStrengths());
    }

    public function testBlankQuadrantIsStoredAsNull(): void
    {
        $this->importer->import([$this->row(['opportunities' => ''])], false);
        $this->entityManager->clear();

        $analysis = $this->analyses->findOneBy(['schoolYear' => '2025-2026']);
        self::assertNotNull($analysis);
        self::assertNull($analysis->getOpportunities());
    }

    public function testDryRunDoesNotPersist(): void
    {
        $report = $this->importer->import([$this->row()], true);
        $this->entityManager->clear();

        self::assertSame(1, $report->getCreated());
        self::assertCount(0, $this->analyses->findAllOrdered());
    }

    public function testRejectsRowWithInvalidSchoolYearFormat(): void
    {
        // Realistic guard: a stray value in the school-year column must be quarantined, not stored.
        $report = $this->importer->import([$this->row(['school_year' => '2025'])], false);
        $this->entityManager->clear();

        self::assertSame(0, $report->getCreated());
        self::assertCount(1, $report->getRejected());
        self::assertCount(0, $this->analyses->findAllOrdered());
    }
}
