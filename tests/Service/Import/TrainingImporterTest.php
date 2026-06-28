<?php

declare(strict_types=1);

namespace App\Tests\Service\Import;

use App\Enum\TrainingType;
use App\Repository\TrainingActionRepository;
use App\Service\Import\TrainingImporter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration tests for the training-plan importer, exercised against the real validator and a real
 * test database (rolled back per test by DAMA). Covers the happy path, the date normalization
 * convention applied to the F.03.0 dirty-text dates ("octubre 2023" -> first of month, "23 al
 * 27/10/23" -> start day), the review-flag path for non-normalizable type/dates (imported with the
 * field left null and flagged, not quarantined), idempotent re-import (upsert by the (year,
 * description, audience) natural key), the optional actual date and the dry-run guard.
 */
final class TrainingImporterTest extends KernelTestCase
{
    private TrainingImporter $importer;
    private TrainingActionRepository $actions;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->importer = $container->get(TrainingImporter::class);
        $this->actions = $container->get(TrainingActionRepository::class);
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
        return array_merge([
            'plan_year' => '2023',
            'description' => 'Curso ISO 14001',
            'type' => 'int',
            'target_audience' => 'Profesorado',
            'objectives' => 'Conocer el sistema ISO 14001 y sus aplicaciones.',
            'planned_date' => '30/10/2023',
            'methodology' => 'Difusión de vídeo y claustro informativo.',
            'actual_date' => '',
            'efficacy_evaluation' => '',
        ], $overrides);
    }

    public function testCreatesActionFromRow(): void
    {
        $report = $this->importer->import([$this->row()], false);
        $this->entityManager->clear();

        self::assertSame(1, $report->getCreated());
        self::assertSame([], $report->getRejected());

        $action = $this->actions->findOneByNaturalKey(2023, 'Curso ISO 14001', 'Profesorado');
        self::assertNotNull($action);
        self::assertSame('2023-10-30', $action->getPlannedDate()->format('Y-m-d'));
        self::assertSame(TrainingType::INTERNAL, $action->getType());
        self::assertNull($action->getActualDate(), 'La fecha real vacía se guarda como null.');
    }

    public function testMonthAndYearPlannedDateBecomesFirstOfMonth(): void
    {
        $report = $this->importer->import([$this->row(['planned_date' => 'octubre 2023'])], false);
        $this->entityManager->clear();

        self::assertSame([], $report->getRejected());
        $action = $this->actions->findOneByNaturalKey(2023, 'Curso ISO 14001', 'Profesorado');
        self::assertNotNull($action);
        self::assertSame('2023-10-01', $action->getPlannedDate()->format('Y-m-d'));
    }

    public function testDayRangePlannedDateBecomesStartDay(): void
    {
        $report = $this->importer->import([$this->row(['planned_date' => '23 al 27/10/23'])], false);
        $this->entityManager->clear();

        self::assertSame([], $report->getRejected());
        $action = $this->actions->findOneByNaturalKey(2023, 'Curso ISO 14001', 'Profesorado');
        self::assertNotNull($action);
        self::assertSame('2023-10-23', $action->getPlannedDate()->format('Y-m-d'));
    }

    public function testNonNormalizablePlannedDateIsImportedAndFlagged(): void
    {
        $report = $this->importer->import(
            [$this->row(['planned_date' => 'A la semana de su incorporación'])],
            false,
        );
        $this->entityManager->clear();

        self::assertSame(1, $report->getCreated());
        self::assertSame([], $report->getRejected());
        self::assertCount(1, $report->getFlagged());
        self::assertStringContainsString('Fecha prevista no normalizable', $report->getFlagged()[0]['reason']);

        $action = $this->actions->findOneByNaturalKey(2023, 'Curso ISO 14001', 'Profesorado');
        self::assertNotNull($action);
        self::assertNull($action->getPlannedDate(), 'La fecha no normalizable se deja null.');
        self::assertTrue($action->isNeedsReview());
        self::assertStringContainsString('Fecha prevista no normalizable', (string) $action->getReviewNote());
    }

    public function testNonNormalizableActualDateIsImportedAndFlagged(): void
    {
        // A non-empty actual date that cannot be normalized is left null and flagged, not dropped.
        $report = $this->importer->import(
            [$this->row(['actual_date' => 'previsto según calendario'])],
            false,
        );
        $this->entityManager->clear();

        self::assertSame(1, $report->getCreated());
        self::assertCount(1, $report->getFlagged());

        $action = $this->actions->findOneByNaturalKey(2023, 'Curso ISO 14001', 'Profesorado');
        self::assertNotNull($action);
        self::assertNull($action->getActualDate());
        self::assertTrue($action->isNeedsReview());
        self::assertStringContainsString('Fecha real no normalizable', (string) $action->getReviewNote());
    }

    public function testAmbiguousTypeIsImportedAndFlagged(): void
    {
        // "int/ext" cannot map to a single delivery mode; imported with type null and flagged.
        $report = $this->importer->import([$this->row(['type' => 'int/ext'])], false);
        $this->entityManager->clear();

        self::assertSame(1, $report->getCreated());
        self::assertCount(1, $report->getFlagged());

        $action = $this->actions->findOneByNaturalKey(2023, 'Curso ISO 14001', 'Profesorado');
        self::assertNotNull($action);
        self::assertNull($action->getType());
        self::assertTrue($action->isNeedsReview());
        self::assertStringContainsString('Tipo EXT/INT no reconocido', (string) $action->getReviewNote());
    }

    public function testCleanRowIsNotFlagged(): void
    {
        $report = $this->importer->import([$this->row()], false);
        $this->entityManager->clear();

        self::assertSame([], $report->getFlagged());
        $action = $this->actions->findOneByNaturalKey(2023, 'Curso ISO 14001', 'Profesorado');
        self::assertNotNull($action);
        self::assertFalse($action->isNeedsReview());
        self::assertNull($action->getReviewNote());
    }

    public function testMultipleNonNormalizableFieldsAccumulateInOneNote(): void
    {
        $report = $this->importer->import([$this->row([
            'type' => 'int/ext',
            'planned_date' => 'sin det',
        ])], false);
        $this->entityManager->clear();

        self::assertCount(1, $report->getFlagged());
        $action = $this->actions->findOneByNaturalKey(2023, 'Curso ISO 14001', 'Profesorado');
        self::assertNotNull($action);
        $note = (string) $action->getReviewNote();
        self::assertStringContainsString('Tipo EXT/INT', $note);
        self::assertStringContainsString('Fecha prevista', $note);
    }

    public function testReimportReappliesFlagOverManualFix(): void
    {
        // Documented contract: the importer is the cutover loader, not a continuous sync, so a full
        // re-run re-sets the review flag from the (still dirty) source — manual UI fixes are meant to
        // happen after the cutover, not to survive another ETL pass. This test pins that behaviour so
        // nobody "fixes" the importer to preserve manual edits without realizing the trade-off.
        $this->importer->import([$this->row(['type' => 'int/ext'])], false);
        $this->entityManager->clear();

        // Simulate the centre fixing the row in the UI and clearing the flag.
        $action = $this->actions->findOneByNaturalKey(2023, 'Curso ISO 14001', 'Profesorado');
        self::assertNotNull($action);
        $action->setType(TrainingType::INTERNAL)->setNeedsReview(false)->setReviewNote(null);
        $this->entityManager->flush();
        $this->entityManager->clear();

        // Re-importing the same dirty CSV row re-flags it (source wins).
        $this->importer->import([$this->row(['type' => 'int/ext'])], false);
        $this->entityManager->clear();

        $reimported = $this->actions->findOneByNaturalKey(2023, 'Curso ISO 14001', 'Profesorado');
        self::assertNotNull($reimported);
        self::assertNull($reimported->getType());
        self::assertTrue($reimported->isNeedsReview());
    }

    public function testReimportIsIdempotentAndUpdatesInPlace(): void
    {
        $this->importer->import([$this->row()], false);
        $this->entityManager->clear();

        $report = $this->importer->import([$this->row(['actual_date' => '30/10/2023'])], false);

        self::assertSame(0, $report->getCreated());
        self::assertSame(1, $report->getUpdated());

        $this->entityManager->clear();
        $found = $this->actions->findBy(['planYear' => 2023, 'description' => 'Curso ISO 14001']);
        self::assertCount(1, $found, 'No debe duplicar: misma clave natural (año, descripción, destinatarios).');
        self::assertSame('2023-10-30', $found[0]->getActualDate()?->format('Y-m-d'));
    }

    public function testSameDescriptionDifferentAudienceAreDistinctActions(): void
    {
        // Real F.03.0 shape: "curso iso 14001" recurs within a year for different audiences. The
        // audience is part of the natural key, so these must NOT collapse into a single action.
        $report = $this->importer->import([
            $this->row(['target_audience' => 'Profesorado']),
            $this->row(['target_audience' => 'Personal no docente']),
        ], false);
        $this->entityManager->clear();

        self::assertSame(2, $report->getCreated());
        $found = $this->actions->findBy(['planYear' => 2023, 'description' => 'Curso ISO 14001']);
        self::assertCount(2, $found, 'Misma descripción y año, distinto destinatario: 2 acciones.');
    }

    public function testRejectsBlankObjectivesViaValidator(): void
    {
        $report = $this->importer->import([$this->row(['objectives' => ''])], false);

        self::assertSame(0, $report->getProcessed());
        self::assertCount(1, $report->getRejected());
        self::assertNull($this->actions->findOneByNaturalKey(2023, 'Curso ISO 14001', 'Profesorado'));
    }

    public function testRejectsBlankDescriptionWithClearReason(): void
    {
        $report = $this->importer->import([$this->row(['description' => ''])], false);

        self::assertSame(0, $report->getProcessed());
        self::assertCount(1, $report->getRejected());
        self::assertStringContainsString('descripción', $report->getRejected()[0]['reason']);
    }

    public function testRejectsBlankTargetAudienceWithClearReason(): void
    {
        $report = $this->importer->import([$this->row(['target_audience' => ''])], false);

        self::assertSame(0, $report->getProcessed());
        self::assertCount(1, $report->getRejected());
        self::assertStringContainsString('destinatario', $report->getRejected()[0]['reason']);
    }

    public function testDuplicateNaturalKeyInSameCsvDoesNotDuplicate(): void
    {
        // A natural key repeated within the same CSV must upsert onto the pending entity, not create
        // a second row (the repository only sees flushed rows, so the in-memory cache is the guard).
        $report = $this->importer->import([
            $this->row(['actual_date' => '']),
            $this->row(['actual_date' => '07/11/2023']),
        ], false);
        $this->entityManager->clear();

        self::assertSame(1, $report->getCreated());
        self::assertSame(1, $report->getUpdated());
        $found = $this->actions->findBy(['planYear' => 2023, 'description' => 'Curso ISO 14001']);
        self::assertCount(1, $found, 'Clave natural duplicada en el mismo CSV: una sola acción.');
        self::assertSame('2023-11-07', $found[0]->getActualDate()?->format('Y-m-d'));
    }

    public function testDryRunDoesNotFlush(): void
    {
        $report = $this->importer->import([$this->row()], true);
        $this->entityManager->clear();

        self::assertSame(1, $report->getCreated());
        self::assertNull($this->actions->findOneByNaturalKey(2023, 'Curso ISO 14001', 'Profesorado'));
    }
}
