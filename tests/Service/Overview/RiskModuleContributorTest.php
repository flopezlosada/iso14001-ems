<?php

declare(strict_types=1);

namespace App\Tests\Service\Overview;

use App\Entity\DafoAnalysis;
use App\Enum\Area;
use App\Enum\ModuleHealth;
use App\Repository\DafoAnalysisRepository;
use App\Repository\RiskAssessmentRepository;
use App\Repository\RiskOpportunityRepository;
use App\Service\Overview\RiskModuleContributor;
use App\Service\RiskWorkflowStatusProvider;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see RiskModuleContributor}: it turns the risk module's workflow status into the
 * concrete pending step shown on the overview. Built on a real {@see RiskWorkflowStatusProvider}
 * over mocked repositories, so the translation of the "just starting" states (no DAFO, no risks
 * identified) is pinned end to end.
 */
final class RiskModuleContributorTest extends TestCase
{
    /**
     * A contributor whose provider sees the given DAFO presence and risk count, and no valuations
     * (the states that do not need built valuation entities).
     */
    private function contributorWith(bool $dafoExists, int $riskCount): RiskModuleContributor
    {
        $dafo = $this->createMock(DafoAnalysisRepository::class);
        $dafo->method('findOneBy')->willReturn($dafoExists ? new DafoAnalysis() : null);

        $risks = $this->createMock(RiskOpportunityRepository::class);
        $risks->method('count')->willReturn($riskCount);

        $assessments = $this->createMock(RiskAssessmentRepository::class);
        $assessments->method('findByExerciseWithActions')->willReturn([]);

        return new RiskModuleContributor(new RiskWorkflowStatusProvider($risks, $assessments, $dafo));
    }

    public function testItSpeaksForTheRiskArea(): void
    {
        self::assertSame(Area::RISK_OPPORTUNITY, $this->contributorWith(true, 1)->area());
    }

    public function testMissingDafoIsSurfacedAsTheStartingStep(): void
    {
        $signal = $this->contributorWith(false, 0)->summarize('2025-2026');

        self::assertNotNull($signal);
        self::assertSame(ModuleHealth::ATTENTION, $signal->health);
        self::assertStringContainsString('DAFO', $signal->summary);
    }

    public function testDafoReadyButNoRisksIdentifiedIsSurfaced(): void
    {
        $signal = $this->contributorWith(true, 0)->summarize('2025-2026');

        self::assertNotNull($signal);
        self::assertSame(ModuleHealth::ATTENTION, $signal->health);
        self::assertStringContainsString('sin riesgos identificados', mb_strtolower($signal->summary));
    }

    public function testUnvaluedRisksAreCountedAsPending(): void
    {
        // DAFO ready, 3 risks, no valuations → all 3 are unvalued.
        $signal = $this->contributorWith(true, 3)->summarize('2025-2026');

        self::assertNotNull($signal);
        self::assertSame(ModuleHealth::ATTENTION, $signal->health);
        self::assertStringContainsString('3 sin valorar', $signal->summary);
    }
}
