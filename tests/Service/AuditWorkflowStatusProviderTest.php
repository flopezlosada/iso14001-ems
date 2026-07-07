<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\NonConformity;
use App\Entity\SystemAudit;
use App\Enum\AuditType;
use App\Enum\NonConformityStatus;
use App\Repository\NonConformityRepository;
use App\Repository\SystemAuditRepository;
use App\Service\AuditWorkflowStatusProvider;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the internal-audit workflow status: the pending signals must be derived correctly
 * from the year's internal audit and its non-conformities.
 */
final class AuditWorkflowStatusProviderTest extends TestCase
{
    public function testReportsNothingPlannedWhenThereIsNoInternalAudit(): void
    {
        $provider = new AuditWorkflowStatusProvider(
            $this->auditsReturning(2025, null),
            $this->createMock(NonConformityRepository::class),
        );

        $status = $provider->for(2025);

        self::assertSame(2025, $status->year);
        self::assertNull($status->internalAuditId);
        self::assertFalse($status->planned);
        self::assertFalse($status->conducted);
        self::assertFalse($status->closed);
        self::assertSame(0, $status->findingsCount);
        self::assertFalse($status->isComplete());
    }

    public function testAPlannedButNotConductedAuditIsNeitherConductedNorClosed(): void
    {
        $audit = (new SystemAudit())->setType(AuditType::INTERNAL)->setYear(2025)->setAuditor('Interna');

        $provider = new AuditWorkflowStatusProvider(
            $this->auditsReturning(2025, $audit),
            $this->nonConformitiesReturning($audit, []),
        );

        $status = $provider->for(2025);

        self::assertTrue($status->planned);
        self::assertFalse($status->conducted);
        self::assertFalse($status->closed);
        self::assertFalse($status->isComplete());
    }

    public function testCountsOpenFindingsOfAConductedAudit(): void
    {
        $audit = (new SystemAudit())
            ->setType(AuditType::INTERNAL)
            ->setYear(2025)
            ->setAuditor('Interna')
            ->setConductedOn(new \DateTimeImmutable('2025-05-10'))
            ->setConclusions('Todo conforme salvo dos hallazgos.');

        $findings = [
            (new NonConformity())->setStatus(NonConformityStatus::OPEN),
            (new NonConformity())->setStatus(NonConformityStatus::IN_TREATMENT),
            (new NonConformity())->setStatus(NonConformityStatus::CLOSED),
        ];

        $provider = new AuditWorkflowStatusProvider(
            $this->auditsReturning(2025, $audit),
            $this->nonConformitiesReturning($audit, $findings),
        );

        $status = $provider->for(2025);

        self::assertTrue($status->conducted);
        self::assertTrue($status->closed);
        self::assertSame(3, $status->findingsCount);
        self::assertSame(2, $status->openFindings);   // open + in treatment, closed excluded
        self::assertFalse($status->isComplete());     // open findings block completion
    }

    public function testIsCompleteWhenClosedWithNoOpenFindings(): void
    {
        $audit = (new SystemAudit())
            ->setType(AuditType::INTERNAL)
            ->setYear(2025)
            ->setAuditor('Interna')
            ->setConductedOn(new \DateTimeImmutable('2025-05-10'))
            ->setConclusions('Cerrada sin incidencias.');

        $provider = new AuditWorkflowStatusProvider(
            $this->auditsReturning(2025, $audit),
            $this->nonConformitiesReturning($audit, [
                (new NonConformity())->setStatus(NonConformityStatus::CLOSED),
            ]),
        );

        self::assertTrue($provider->for(2025)->isComplete());
    }

    private function auditsReturning(int $year, ?SystemAudit $audit): SystemAuditRepository
    {
        $repo = $this->createMock(SystemAuditRepository::class);
        $repo->method('findInternalForYear')->with($year)->willReturn($audit);

        return $repo;
    }

    /**
     * @param NonConformity[] $findings
     */
    private function nonConformitiesReturning(SystemAudit $audit, array $findings): NonConformityRepository
    {
        $repo = $this->createMock(NonConformityRepository::class);
        $repo->method('findByAudit')->with($audit)->willReturn($findings);

        return $repo;
    }
}
