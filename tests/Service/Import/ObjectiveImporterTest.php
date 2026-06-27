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
 * Covers the happy path, idempotent upsert by reference, the free-text target period and rejection
 * of an unknown status.
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
            'reference' => 'OBJ.01',
            'sequence' => '1',
            'description' => 'Reducir el consumo energético en un 5%.',
            'target_period' => 'enero de 2025 a marzo de 2026',
            'status' => 'in_progress',
        ], $overrides);
    }

    public function testCreatesObjective(): void
    {
        $report = $this->importer->import([$this->row()], false);
        $this->entityManager->clear();

        self::assertSame(1, $report->getCreated());
        self::assertSame([], $report->getRejected());

        $objective = $this->objectives->findOneBy(['reference' => 'OBJ.01']);
        self::assertNotNull($objective);
        self::assertSame(ObjectiveStatus::IN_PROGRESS, $objective->getStatus());
        self::assertSame('enero de 2025 a marzo de 2026', $objective->getTargetPeriod());
        // Legacy exports carry no school year, so the current course is applied as a fallback.
        self::assertSame('2025-2026', $objective->getSchoolYear());
    }

    public function testHonoursAnExplicitSchoolYearNormalisingTheSeparator(): void
    {
        // Source carries the slash form; it must be stored hyphenated like the entity expects.
        $report = $this->importer->import([$this->row(['school_year' => '2024/2025'])], false);
        $this->entityManager->clear();

        self::assertSame(1, $report->getCreated());
        self::assertSame('2024-2025', $this->objectives->findOneBy(['reference' => 'OBJ.01'])->getSchoolYear());
    }

    public function testReimportIsIdempotentByReference(): void
    {
        $this->importer->import([$this->row()], false);
        $this->entityManager->clear();

        $report = $this->importer->import([$this->row(['status' => 'achieved'])], false);
        $this->entityManager->clear();

        self::assertSame(0, $report->getCreated());
        self::assertSame(1, $report->getUpdated());

        $found = $this->objectives->findBy(['reference' => 'OBJ.01']);
        self::assertCount(1, $found, 'No debe duplicar: misma referencia.');
        self::assertSame(ObjectiveStatus::ACHIEVED, $found[0]->getStatus());
    }

    public function testRejectsUnknownStatusWithoutPersisting(): void
    {
        $report = $this->importer->import([$this->row(['status' => 'levitating'])], false);

        self::assertSame(0, $report->getProcessed());
        self::assertCount(1, $report->getRejected());
        self::assertStringContainsString('Estado de objetivo desconocido', $report->getRejected()[0]['reason']);
    }
}
