<?php

declare(strict_types=1);

namespace App\Tests\Service\Import;

use App\Enum\ObjectiveStatus;
use App\Repository\ObjectiveRepository;
use App\Service\Import\ObjectiveImporter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration tests for the objectives importer over a real test database (rolled back per test).
 * Covers the happy path, the per-course code reused across years (the same "OBJ.01" must NOT
 * collapse the courses), idempotent upsert by (school year, source code), separator normalisation
 * and rejection of bad rows.
 */
final class ObjectiveImporterTest extends KernelTestCase
{
    private ObjectiveImporter $importer;
    private ObjectiveRepository $objectives;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->importer = $container->get(ObjectiveImporter::class);
        $this->objectives = $container->get(ObjectiveRepository::class);
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
            'source_code' => 'OBJ.01',
            'school_year' => '2025-2026',
            'description' => 'Reducir el consumo energético en un 5%.',
            'target_period' => 'enero de 2025 a marzo de 2026',
            'status' => 'in_progress',
        ], $overrides);
    }

    public function testCreatesObjectiveWithAGeneratedGlobalReference(): void
    {
        $report = $this->importer->import([$this->row()], false);
        $this->entityManager->clear();

        self::assertSame(1, $report->getCreated());
        self::assertSame([], $report->getRejected());

        $objective = $this->objectives->findOneBy(['schoolYear' => '2025-2026', 'sourceCode' => 'OBJ.01']);
        self::assertNotNull($objective);
        self::assertSame(ObjectiveStatus::IN_PROGRESS, $objective->getStatus());
        self::assertSame('enero de 2025 a marzo de 2026', $objective->getTargetPeriod());
        // The reference is a global surrogate assigned by the importer, not the centre's code.
        self::assertSame('OBJ-01', $objective->getReference());
        self::assertSame('OBJ.01', $objective->getSourceCode());
    }

    public function testSameCodeInDifferentCoursesCreatesTwoObjectives(): void
    {
        // The crux of the multi-year import: "OBJ.01" exists once per course and must NOT collapse.
        $report = $this->importer->import([
            $this->row(['school_year' => '2023-2024', 'description' => 'Objetivo del curso 23-24.']),
            $this->row(['school_year' => '2024-2025', 'description' => 'Objetivo del curso 24-25.']),
        ], false);
        $this->entityManager->clear();

        self::assertSame(2, $report->getCreated());

        $first = $this->objectives->findOneBy(['schoolYear' => '2023-2024', 'sourceCode' => 'OBJ.01']);
        $second = $this->objectives->findOneBy(['schoolYear' => '2024-2025', 'sourceCode' => 'OBJ.01']);
        self::assertNotNull($first);
        self::assertNotNull($second);
        self::assertNotSame($first->getReference(), $second->getReference(), 'Cada curso tiene su propia referencia global.');
    }

    public function testSameCourseAndCodeTwiceInOneRunMergesInsteadOfDuplicating(): void
    {
        // findOneBy cannot see the first one (not flushed yet); the in-call identity map must merge
        // them instead of persisting two rows and tripping the unique (school_year, source_code).
        $report = $this->importer->import([
            $this->row(['description' => 'Primera redacción.']),
            $this->row(['description' => 'Redacción corregida.']),
        ], false);
        $this->entityManager->clear();

        self::assertSame(1, $report->getCreated());
        self::assertSame(1, $report->getUpdated());

        $found = $this->objectives->findBy(['schoolYear' => '2025-2026', 'sourceCode' => 'OBJ.01']);
        self::assertCount(1, $found, 'No debe duplicar el mismo (curso, código) en una pasada.');
        self::assertSame('Redacción corregida.', $found[0]->getDescription());
    }

    public function testHonoursSchoolYearNormalisingTheSeparator(): void
    {
        // Source carries the slash form; it must be stored hyphenated like the entity expects.
        $report = $this->importer->import([$this->row(['school_year' => '2024/2025'])], false);
        $this->entityManager->clear();

        self::assertSame(1, $report->getCreated());
        self::assertNotNull($this->objectives->findOneBy(['schoolYear' => '2024-2025', 'sourceCode' => 'OBJ.01']));
    }

    public function testReimportIsIdempotentByCourseAndCode(): void
    {
        $this->importer->import([$this->row()], false);
        $this->entityManager->clear();

        $report = $this->importer->import([$this->row(['status' => 'achieved'])], false);
        $this->entityManager->clear();

        self::assertSame(0, $report->getCreated());
        self::assertSame(1, $report->getUpdated());

        $found = $this->objectives->findBy(['schoolYear' => '2025-2026', 'sourceCode' => 'OBJ.01']);
        self::assertCount(1, $found, 'No debe duplicar: mismo curso y mismo código.');
        self::assertSame(ObjectiveStatus::ACHIEVED, $found[0]->getStatus());
    }

    public function testRejectsUnknownStatusWithoutPersisting(): void
    {
        $report = $this->importer->import([$this->row(['status' => 'levitating'])], false);

        self::assertSame(0, $report->getProcessed());
        self::assertCount(1, $report->getRejected());
        self::assertStringContainsString('Estado de objetivo desconocido', $report->getRejected()[0]['reason']);
    }

    public function testRejectsRowWithoutSourceCode(): void
    {
        $report = $this->importer->import([$this->row(['source_code' => ''])], false);

        self::assertSame(0, $report->getProcessed());
        self::assertCount(1, $report->getRejected());
        self::assertStringContainsString('source_code', $report->getRejected()[0]['reason']);
    }

    public function testRejectsRowWithoutASchoolYear(): void
    {
        // No course can be derived: the entity validation (NotBlank/Regex) must quarantine the row.
        $report = $this->importer->import([$this->row(['school_year' => ''])], false);

        self::assertSame(0, $report->getProcessed());
        self::assertCount(1, $report->getRejected());
        self::assertStringContainsString('schoolYear', $report->getRejected()[0]['reason']);
    }
}
