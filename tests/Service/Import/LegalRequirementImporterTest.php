<?php

declare(strict_types=1);

namespace App\Tests\Service\Import;

use App\Enum\ComplianceStatus;
use App\Enum\EvaluationFrequency;
use App\Enum\LegalScope;
use App\Repository\LegalRequirementRepository;
use App\Service\Import\LegalRequirementImporter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration tests for the legal requirements importer over a real test database (rolled back per
 * test). Covers the happy path, idempotent upsert by reference, frequency/date parsing, the
 * untouched compliance status and rejection of an unknown scope.
 */
final class LegalRequirementImporterTest extends KernelTestCase
{
    private LegalRequirementImporter $importer;
    private LegalRequirementRepository $requirements;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->importer = $container->get(LegalRequirementImporter::class);
        $this->requirements = $container->get(LegalRequirementRepository::class);
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
            'reference' => 'RL-01',
            'sequence' => '1',
            'legal_provision' => 'Ley 7/2022, de residuos y suelos contaminados',
            'scope' => 'national',
            'environmental_vector' => 'Residuos',
            'specific_requirement' => 'Tener contratos con gestores autorizados.',
            'compliance_evidence' => 'Registros de gestores autorizados',
            'evaluation_frequency' => 'annual',
            'last_reviewed_on' => '2026-02-01',
            'next_review_on' => '',
        ], $overrides);
    }

    public function testCreatesRequirement(): void
    {
        $report = $this->importer->import([$this->row()], false);
        $this->entityManager->clear();

        self::assertSame(1, $report->getCreated());
        self::assertSame([], $report->getRejected());

        $req = $this->requirements->findOneBy(['reference' => 'RL-01']);
        self::assertNotNull($req);
        self::assertSame(LegalScope::NATIONAL, $req->getScope());
        self::assertSame(EvaluationFrequency::ANNUAL, $req->getEvaluationFrequency());
        self::assertSame('2026-02-01', $req->getLastReviewedOn()?->format('Y-m-d'));
        self::assertSame(ComplianceStatus::PENDING, $req->getComplianceStatus(), 'La fuente no trae estado: queda en el default.');
    }

    public function testReimportIsIdempotentByReference(): void
    {
        $this->importer->import([$this->row()], false);
        $this->entityManager->clear();

        $report = $this->importer->import([$this->row(['legal_provision' => 'Ley 7/2022 (revisada)'])], false);
        $this->entityManager->clear();

        self::assertSame(0, $report->getCreated());
        self::assertSame(1, $report->getUpdated());

        $found = $this->requirements->findBy(['reference' => 'RL-01']);
        self::assertCount(1, $found, 'No debe duplicar: misma referencia.');
        self::assertSame('Ley 7/2022 (revisada)', $found[0]->getLegalProvision());
    }

    public function testNullableFieldsAndFrequencyAreOptional(): void
    {
        $report = $this->importer->import([$this->row([
            'environmental_vector' => '',
            'compliance_evidence' => '',
            'evaluation_frequency' => '',
            'last_reviewed_on' => '',
        ])], false);
        $this->entityManager->clear();

        self::assertSame([], $report->getRejected());
        $req = $this->requirements->findOneBy(['reference' => 'RL-01']);
        self::assertNotNull($req);
        self::assertNull($req->getEnvironmentalVector());
        self::assertNull($req->getEvaluationFrequency());
        self::assertNull($req->getLastReviewedOn());
    }

    public function testRejectsUnknownScopeWithoutPersisting(): void
    {
        $report = $this->importer->import([$this->row(['scope' => 'galactic'])], false);

        self::assertSame(0, $report->getProcessed());
        self::assertCount(1, $report->getRejected());
        self::assertStringContainsString('Ámbito legal desconocido', $report->getRejected()[0]['reason']);
    }
}
