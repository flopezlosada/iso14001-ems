<?php

declare(strict_types=1);

namespace App\Tests\Service\Import;

use App\Entity\RiskAction;
use App\Entity\RiskAssessment;
use App\Entity\RiskOpportunity;
use App\Entity\Role;
use App\Enum\RiskCategory;
use App\Enum\RiskLevel;
use App\Enum\RiskOpportunityType;
use App\Repository\ProcessAreaRepository;
use App\Repository\RiskOpportunityRepository;
use App\Service\Import\RiskImporter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration tests for the risks-and-opportunities importer over a real test database (rolled back
 * per test). Covers process-area upsert, the recomputed score/category (never read from the sheet),
 * the per-exercise assessment, the optional action with free-text deadline, idempotent re-import by
 * (type, description) and rejection of invalid rows.
 */
final class RiskImporterTest extends KernelTestCase
{
    private RiskImporter $importer;
    private RiskOpportunityRepository $risks;
    private ProcessAreaRepository $processAreas;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->importer = $container->get(RiskImporter::class);
        $this->risks = $container->get(RiskOpportunityRepository::class);
        $this->processAreas = $container->get(ProcessAreaRepository::class);
        $this->entityManager = $container->get(EntityManagerInterface::class);

        // The importer maps the sheet's "RESPO SGMA" to the 'ems_manager' role; it must exist to resolve.
        $this->entityManager->persist((new Role())->setCode('ems_manager')->setName('Responsable del SGA'));
        $this->entityManager->flush();
    }

    /**
     * The risk/opportunity matching a description, reloaded with its assessments and actions.
     */
    private function findByDescription(string $description): ?RiskOpportunity
    {
        $risk = $this->risks->findOneBy(['description' => $description]);

        return null === $risk ? null : $this->risks->findWithAssessmentsAndActions((int) $risk->getId());
    }

    /**
     * The single assessment of a risk for a given exercise, asserting exactly one exists.
     */
    private function assessmentFor(RiskOpportunity $risk, string $exercise): RiskAssessment
    {
        $matches = array_values(array_filter(
            $risk->getAssessments()->toArray(),
            static fn (RiskAssessment $a) => $a->getExercise() === $exercise,
        ));
        self::assertCount(1, $matches, sprintf('Debe haber una valoración para el curso %s.', $exercise));

        return $matches[0];
    }

    /**
     * @param array<string, string> $overrides
     *
     * @return array<string, string>
     */
    private function row(array $overrides = []): array
    {
        return array_merge([
            'exercise' => '2024-2025',
            'type' => 'risk',
            'process_area' => 'AREA FORMACION',
            'description' => 'Falta de conocimientos ambientales del personal.',
            'probability' => '2',
            'impact' => '2',
            'justification' => '',
            'action' => 'Plan de formación ambiental anual.',
            'responsible' => 'RESPO SGMA',
            'deadline' => 'ANUAL',
            'efficacy' => 'Si',
            'evaluated_at' => '2024-10-01',
        ], $overrides);
    }

    public function testCreatesRiskWithAssessmentActionAndUpsertsProcessArea(): void
    {
        $report = $this->importer->import([$this->row()], false);
        $this->entityManager->clear();

        self::assertSame(1, $report->getCreated());
        self::assertSame([], $report->getRejected());

        $risk = $this->findByDescription('Falta de conocimientos ambientales del personal.');
        self::assertNotNull($risk);
        self::assertSame(RiskOpportunityType::RISK, $risk->getType());
        self::assertSame('AREA FORMACION', $risk->getProcessArea()->getName());
        self::assertTrue($risk->getProcessArea()->isActive());

        $assessment = $this->assessmentFor($risk, '2024-2025');
        self::assertSame(RiskLevel::MEDIUM, $assessment->getProbability());
        self::assertSame(RiskLevel::MEDIUM, $assessment->getImpact());

        self::assertCount(1, $assessment->getActions());
        $action = $assessment->getActions()->first();
        self::assertInstanceOf(RiskAction::class, $action);
        self::assertSame('ANUAL', $action->getDeadline(), 'El plazo en texto se conserva tal cual.');
        self::assertSame('2024-10-01', $action->getEvaluatedAt()?->format('Y-m-d'));
        // "RESPO SGMA" resolves to the ems_manager role.
        self::assertSame('ems_manager', $action->getResponsible()?->getCode());
    }

    public function testUnknownResponsibleIsLeftNullAndFlagged(): void
    {
        $report = $this->importer->import([$this->row(['responsible' => 'RESPO DESCONOCIDO'])], false);
        $this->entityManager->clear();

        // Not a rejection (the row is imported), but flagged for manual review, with no responsible.
        self::assertSame(1, $report->getCreated());
        self::assertNotEmpty($report->getFlagged());

        $risk = $this->findByDescription('Falta de conocimientos ambientales del personal.');
        self::assertNotNull($risk);
        $action = $this->assessmentFor($risk, '2024-2025')->getActions()->first();
        self::assertInstanceOf(RiskAction::class, $action);
        self::assertNull($action->getResponsible());
    }

    public function testScoreAndCategoryAreComputedNotReadFromSheet(): void
    {
        // 2 × 2 = 4 → MODERADO per the PC.03.0 bands, regardless of what the sheet's PxI/CATEGORIA
        // columns say (those are dropped by the normalizer and never reach the importer).
        $this->importer->import([$this->row(['probability' => '2', 'impact' => '2'])], false);
        $this->entityManager->clear();

        $risk = $this->findByDescription('Falta de conocimientos ambientales del personal.');
        self::assertNotNull($risk);
        $assessment = $this->assessmentFor($risk, '2024-2025');
        self::assertSame(4, $assessment->getScore());
        self::assertSame(RiskCategory::MODERATE, $assessment->getCategory());
    }

    public function testSameRiskAcrossTwoExercisesIsOneItemWithTwoAssessments(): void
    {
        $report = $this->importer->import([
            $this->row(['exercise' => '2024-2025']),
            $this->row(['exercise' => '2025-2026', 'probability' => '3', 'impact' => '3']),
        ], false);
        $this->entityManager->clear();

        self::assertSame(1, $report->getCreated(), 'El segundo curso reusa el mismo riesgo.');
        self::assertSame(1, $report->getUpdated());

        $found = $this->risks->findBy(['description' => 'Falta de conocimientos ambientales del personal.']);
        self::assertCount(1, $found, 'El mismo riesgo no se duplica entre cursos.');

        $risk = $this->findByDescription('Falta de conocimientos ambientales del personal.');
        self::assertNotNull($risk);
        self::assertCount(2, $risk->getAssessments());
        self::assertSame(RiskCategory::CRITICAL, $this->assessmentFor($risk, '2025-2026')->getCategory());
    }

    public function testReimportIsIdempotentAndUpdatesAssessmentInPlace(): void
    {
        $this->importer->import([$this->row()], false);
        $this->entityManager->clear();

        $report = $this->importer->import([$this->row(['probability' => '3', 'impact' => '3'])], false);
        $this->entityManager->clear();

        self::assertSame(0, $report->getCreated());
        self::assertSame(1, $report->getUpdated());

        $risk = $this->findByDescription('Falta de conocimientos ambientales del personal.');
        self::assertNotNull($risk);
        self::assertCount(1, $risk->getAssessments(), 'No debe duplicar la valoración del mismo curso.');
        self::assertSame(9, $this->assessmentFor($risk, '2024-2025')->getScore(), 'La valoración se actualiza en sitio.');
    }

    public function testTwoRisksShareProcessAreaWithoutDuplicatingIt(): void
    {
        $this->importer->import([
            $this->row(['description' => 'Riesgo A.']),
            $this->row(['description' => 'Riesgo B.']),
        ], false);
        $this->entityManager->clear();

        self::assertCount(1, $this->processAreas->findBy(['name' => 'AREA FORMACION']), 'El área se reutiliza, no se duplica.');
    }

    public function testRowWithoutActionLeavesAssessmentWithNoActions(): void
    {
        $this->importer->import([$this->row(['action' => ''])], false);
        $this->entityManager->clear();

        $risk = $this->findByDescription('Falta de conocimientos ambientales del personal.');
        self::assertNotNull($risk);
        self::assertCount(0, $this->assessmentFor($risk, '2024-2025')->getActions());
    }

    public function testRejectsOutOfRangeProbabilityWithoutPersisting(): void
    {
        $report = $this->importer->import([$this->row(['probability' => '5'])], false);

        self::assertSame(0, $report->getProcessed());
        self::assertCount(1, $report->getRejected());
        self::assertNull($this->findByDescription('Falta de conocimientos ambientales del personal.'));
    }

    public function testRejectsUnknownTypeWithoutPersisting(): void
    {
        $report = $this->importer->import([$this->row(['type' => 'cosmic_ray'])], false);

        self::assertSame(0, $report->getProcessed());
        self::assertCount(1, $report->getRejected());
        self::assertStringContainsString('Tipo desconocido', $report->getRejected()[0]['reason']);
    }
}
