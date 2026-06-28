<?php

declare(strict_types=1);

namespace App\Tests\Service\ManagementReview\Provider;

use App\Entity\CorrectiveAction;
use App\Entity\NonConformity;
use App\Entity\SystemAudit;
use App\Entity\User;
use App\Enum\AuditType;
use App\Enum\NonConformityOrigin;
use App\Enum\NonConformityStatus;
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

    public function testSummarisesTheClosingYearAuditsWithTheirFindingsAndResolutionPlan(): void
    {
        $audits = $this->createMock(SystemAuditRepository::class);
        // Course "2025-2026" -> closing year 2026 (ExerciseYears::endYear).
        $audits->expects(self::once())->method('findForYear')->with(2026)->willReturn([
            $this->audit(AuditType::INTERNAL, 2026, '2026-01-22', 'Auditora interna', null),
            $this->audit(AuditType::EXTERNAL, 2026, null, 'Entidad certificadora', 'Sistema conforme.'),
        ]);

        $withPlan = (new NonConformity())
            ->setReference('NC-AI-2026-01')
            ->setOrigin(NonConformityOrigin::INTERNAL_AUDIT)
            ->setDescription('Fuga en la línea de refrigeración')
            ->setStatus(NonConformityStatus::OPEN)
            ->addCorrectiveAction(
                (new CorrectiveAction())
                    ->setDescription('Sustituir la junta defectuosa')
                    ->setResponsible((new User())->setFullName('Juan Pérez'))
                    ->setPlannedDate(new \DateTimeImmutable('2026-03-15')),
            );
        $withoutPlan = (new NonConformity())
            ->setReference('NC-AE-2026-01')
            ->setOrigin(NonConformityOrigin::EXTERNAL_AUDIT)
            ->setDescription('Falta el registro de un residuo peligroso')
            ->setStatus(NonConformityStatus::CLOSED);

        $ncs = $this->createMock(NonConformityRepository::class);
        $ncs->method('findAuditFindingsForYear')->with(2026)->willReturn([$withPlan, $withoutPlan]);

        $summary = $this->provider($audits, $ncs)->summarize('2025-2026');

        self::assertStringContainsString('Auditorías del año 2026: 2.', $summary);
        self::assertStringContainsString('- Auditoría Interna (22/01/2026), Auditora interna.', $summary);
        // Null conducted date renders as "sin fecha"; conclusions only show when present.
        self::assertStringContainsString('- Auditoría Externa (sin fecha), Entidad certificadora. Conclusiones: Sistema conforme.', $summary);
        self::assertStringContainsString('No conformidades detectadas en auditoría: 2.', $summary);
        // Each finding: reference, origin, description and status.
        self::assertStringContainsString('- NC-AI-2026-01 (Auditoría interna): Fuga en la línea de refrigeración [Abierta]', $summary);
        // Its resolution plan: corrective action with responsible and due date.
        self::assertStringContainsString('Plan de resolución: Sustituir la junta defectuosa (responsable: Juan Pérez; prevista: 15/03/2026)', $summary);
        // A finding without corrective actions states the plan is still pending.
        self::assertStringContainsString('- NC-AE-2026-01 (Auditoría externa): Falta el registro de un residuo peligroso [Cerrada]', $summary);
        self::assertStringContainsString('Plan de resolución: pendiente de definir.', $summary);
    }

    public function testReturnsEmptyWhenNoAuditsForTheYear(): void
    {
        $audits = $this->createMock(SystemAuditRepository::class);
        $audits->method('findForYear')->with(2026)->willReturn([]);

        $ncs = $this->createMock(NonConformityRepository::class);
        // No audits -> the provider returns early without looking up findings.
        $ncs->expects(self::never())->method('findAuditFindingsForYear');

        self::assertSame('', $this->provider($audits, $ncs)->summarize('2025-2026'));
    }

    public function testOmitsFindingsBlockWhenNoAuditNonConformities(): void
    {
        $audits = $this->createMock(SystemAuditRepository::class);
        $audits->method('findForYear')->with(2026)->willReturn([
            $this->audit(AuditType::INTERNAL, 2026, '2026-02-10', 'Auditora interna', null),
        ]);

        $ncs = $this->createMock(NonConformityRepository::class);
        $ncs->method('findAuditFindingsForYear')->with(2026)->willReturn([]);

        $summary = $this->provider($audits, $ncs)->summarize('2025-2026');

        self::assertStringContainsString('Auditorías del año 2026: 1.', $summary);
        self::assertStringNotContainsString('No conformidades detectadas', $summary);
    }
}
