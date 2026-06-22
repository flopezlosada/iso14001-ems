<?php

declare(strict_types=1);

namespace App\Tests\Service\Import;

use App\Entity\CorrectiveAction;
use App\Entity\NonConformity;
use App\Enum\Efficacy;
use App\Enum\NonConformityOrigin;
use App\Enum\NonConformityStatus;
use App\Repository\NonConformityRepository;
use App\Service\Import\NonConformityImporter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration tests for the non-conformities importer over a real test database (rolled back per
 * test). Covers reference reconstruction, the corrective action, closed/in-treatment status, the
 * closure validation rule, idempotent re-import by reference and rejection of invalid rows.
 */
final class NonConformityImporterTest extends KernelTestCase
{
    private NonConformityImporter $importer;
    private NonConformityRepository $nonConformities;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->importer = $container->get(NonConformityImporter::class);
        $this->nonConformities = $container->get(NonConformityRepository::class);
        $this->entityManager = $container->get(EntityManagerInterface::class);
    }

    /**
     * Returns the single corrective action of a non-conformity, asserting there is one (keeps the
     * type checker happy: Collection::first() is CorrectiveAction|false).
     */
    private function onlyAction(NonConformity $nc): CorrectiveAction
    {
        self::assertCount(1, $nc->getCorrectiveActions());
        $action = $nc->getCorrectiveActions()->first();
        self::assertInstanceOf(CorrectiveAction::class, $action);

        return $action;
    }

    /**
     * @param array<string, string> $overrides
     *
     * @return array<string, string>
     */
    private function row(array $overrides = []): array
    {
        return [
            'origin' => 'external_audit',
            'origin_detail' => 'Auditoría externa Fase I',
            'year' => '2024',
            'sequence' => '1',
            'iso_clause' => '',
            'description' => 'Hallazgo de auditoría de ejemplo para el test.',
            'root_cause' => 'Causa raíz de ejemplo.',
            'status' => 'closed',
            'opened_at' => '2024-01-12',
            'closed_at' => '2024-03-01',
            'action_description' => 'Acción correctiva de ejemplo [Plazo: 6 meses]',
            'action_efficacy' => 'ok',
        ] + $overrides;
    }

    public function testCreatesNonConformityWithReconstructedReferenceAndAction(): void
    {
        $report = $this->importer->import([$this->row()], false);
        $this->entityManager->clear();

        self::assertSame(1, $report->getCreated());
        self::assertSame([], $report->getRejected());

        $nc = $this->nonConformities->findOneBy(['reference' => 'NC.AE.2024.01']);
        self::assertNotNull($nc);
        self::assertSame(NonConformityOrigin::EXTERNAL_AUDIT, $nc->getOrigin());
        self::assertSame('Auditoría externa Fase I', $nc->getOriginDetail());
        self::assertSame(NonConformityStatus::CLOSED, $nc->getStatus());
        self::assertSame('2024-03-01', $nc->getClosedAt()?->format('Y-m-d'));

        self::assertSame(Efficacy::OK, $this->onlyAction($nc)->getEfficacy());
    }

    public function testInTreatmentNonConformityHasNoCloseDate(): void
    {
        $report = $this->importer->import([$this->row([
            'sequence' => '10',
            'status' => 'in_treatment',
            'closed_at' => '',
            'action_efficacy' => '',
        ])], false);
        $this->entityManager->clear();

        self::assertSame(1, $report->getCreated());

        $nc = $this->nonConformities->findOneBy(['reference' => 'NC.AE.2024.10']);
        self::assertNotNull($nc);
        self::assertSame(NonConformityStatus::IN_TREATMENT, $nc->getStatus());
        self::assertNull($nc->getClosedAt());
        self::assertNull($this->onlyAction($nc)->getEfficacy());
    }

    public function testReimportIsIdempotentAndReplacesAction(): void
    {
        $this->importer->import([$this->row()], false);
        $this->entityManager->clear();

        $report = $this->importer->import([$this->row(['action_description' => 'Acción revisada [Plazo: 6 meses]'])], false);
        $this->entityManager->clear();

        self::assertSame(0, $report->getCreated());
        self::assertSame(1, $report->getUpdated());

        $found = $this->nonConformities->findBy(['reference' => 'NC.AE.2024.01']);
        self::assertCount(1, $found, 'No debe duplicar: misma referencia.');
        self::assertStringContainsString('Acción revisada', $this->onlyAction($found[0])->getDescription(), 'La acción correctiva se reemplaza, no se acumula.');
    }

    public function testRejectsClosedWithoutEffectiveAction(): void
    {
        // Closure rule (PC.10.0): a closed NC needs every corrective action reviewed effective.
        $report = $this->importer->import([$this->row(['action_efficacy' => ''])], false);

        self::assertSame(0, $report->getProcessed());
        self::assertCount(1, $report->getRejected());
        self::assertNull($this->nonConformities->findOneBy(['reference' => 'NC.AE.2024.01']));
    }

    public function testRejectsUnknownOriginWithoutPersisting(): void
    {
        $report = $this->importer->import([$this->row(['origin' => 'cosmic_ray'])], false);

        self::assertSame(0, $report->getProcessed());
        self::assertCount(1, $report->getRejected());
        self::assertStringContainsString('Origen desconocido', $report->getRejected()[0]['reason']);
    }

    public function testRejectsMissingOpenedAtWithoutPersisting(): void
    {
        $report = $this->importer->import([$this->row(['opened_at' => ''])], false);

        self::assertSame(0, $report->getProcessed());
        self::assertCount(1, $report->getRejected());
    }
}
