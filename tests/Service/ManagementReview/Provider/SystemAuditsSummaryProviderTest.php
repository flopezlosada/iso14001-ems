<?php

declare(strict_types=1);

namespace App\Tests\Service\ManagementReview\Provider;

use App\Entity\SystemAudit;
use App\Enum\AuditType;
use App\Enum\NonConformityOrigin;
use App\Repository\NonConformityRepository;
use App\Repository\SystemAuditRepository;
use App\Service\ManagementReview\Provider\SystemAuditsSummaryProvider;
use PHPUnit\Framework\TestCase;

final class SystemAuditsSummaryProviderTest extends TestCase
{
    /**
     * Builds an in-memory audit; a null conducted date / null conclusions mirror real records
     * (a planned audit with no date yet, or one whose conclusions are not formalised).
     */
    private function audit(AuditType $type, int $year, ?string $conductedOn, string $auditor, ?string $conclusions): SystemAudit
    {
        return (new SystemAudit())
            ->setType($type)
            ->setYear($year)
            ->setConductedOn(null !== $conductedOn ? new \DateTimeImmutable($conductedOn) : null)
            ->setAuditor($auditor)
            ->setConclusions($conclusions);
    }

    private function provider(SystemAuditRepository $audits, NonConformityRepository $ncs): SystemAuditsSummaryProvider
    {
        return new SystemAuditsSummaryProvider($audits, $ncs);
    }

    public function testSummarisesTheClosingYearAuditsWithTheirFindings(): void
    {
        $audits = $this->createMock(SystemAuditRepository::class);
        // Course "2025-2026" -> closing year 2026 (ExerciseYears::endYear).
        $audits->expects(self::once())->method('findForYear')->with(2026)->willReturn([
            $this->audit(AuditType::INTERNAL, 2026, '2026-01-22', 'Auditora interna', null),
            $this->audit(AuditType::EXTERNAL, 2026, null, 'Entidad certificadora', 'Sistema conforme.'),
        ]);

        $ncs = $this->createMock(NonConformityRepository::class);
        $ncs->method('countAuditFindingsByOriginForYear')->with(2026)->willReturn([
            NonConformityOrigin::INTERNAL_AUDIT->value => 2,
            NonConformityOrigin::EXTERNAL_AUDIT->value => 1,
        ]);

        $summary = $this->provider($audits, $ncs)->summarize('2025-2026');

        self::assertStringContainsString('Auditorías del año 2026: 2.', $summary);
        self::assertStringContainsString('- Auditoría Interna (22/01/2026), Auditora interna.', $summary);
        // Null conducted date renders as "sin fecha"; conclusions only show when present.
        self::assertStringContainsString('- Auditoría Externa (sin fecha), Entidad certificadora. Conclusiones: Sistema conforme.', $summary);
        self::assertStringContainsString('No conformidades detectadas en auditoría: 3 (interna: 2, externa: 1).', $summary);
    }

    public function testReturnsEmptyWhenNoAuditsForTheYear(): void
    {
        $audits = $this->createMock(SystemAuditRepository::class);
        $audits->method('findForYear')->with(2026)->willReturn([]);

        $ncs = $this->createMock(NonConformityRepository::class);
        // No audits -> the provider returns early without looking up findings.
        $ncs->expects(self::never())->method('countAuditFindingsByOriginForYear');

        self::assertSame('', $this->provider($audits, $ncs)->summarize('2025-2026'));
    }

    public function testOmitsFindingsLineWhenNoAuditNonConformities(): void
    {
        $audits = $this->createMock(SystemAuditRepository::class);
        $audits->method('findForYear')->with(2026)->willReturn([
            $this->audit(AuditType::INTERNAL, 2026, '2026-02-10', 'Auditora interna', null),
        ]);

        $ncs = $this->createMock(NonConformityRepository::class);
        $ncs->method('countAuditFindingsByOriginForYear')->with(2026)->willReturn([]);

        $summary = $this->provider($audits, $ncs)->summarize('2025-2026');

        self::assertStringContainsString('Auditorías del año 2026: 1.', $summary);
        self::assertStringNotContainsString('No conformidades detectadas', $summary);
    }
}
