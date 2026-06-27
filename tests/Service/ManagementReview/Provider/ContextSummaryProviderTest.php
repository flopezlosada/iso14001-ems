<?php

declare(strict_types=1);

namespace App\Tests\Service\ManagementReview\Provider;

use App\Entity\DafoAnalysis;
use App\Repository\DafoAnalysisRepository;
use App\Service\ManagementReview\Provider\ContextSummaryProvider;
use PHPUnit\Framework\TestCase;

final class ContextSummaryProviderTest extends TestCase
{
    /**
     * Builds an in-memory DAFO analysis; null quadrants mirror real data (a course may leave a
     * quadrant blank, as the seed does for the 2024-2025 threats).
     */
    private function analysis(
        string $schoolYear,
        ?string $weaknesses,
        ?string $threats,
        ?string $strengths,
        ?string $opportunities,
    ): DafoAnalysis {
        return (new DafoAnalysis())
            ->setSchoolYear($schoolYear)
            ->setWeaknesses($weaknesses)
            ->setThreats($threats)
            ->setStrengths($strengths)
            ->setOpportunities($opportunities);
    }

    public function testSummarisesOnlyTheFilledQuadrantsOfTheMatchingCourse(): void
    {
        $repo = $this->createMock(DafoAnalysisRepository::class);
        $repo->method('findAllOrdered')->willReturn([
            $this->analysis('2025-2026', 'Financiación dependiente', 'Cambio climático', 'Personal cualificado', null),
            $this->analysis('2024-2025', 'De otro curso', null, null, null),
        ]);

        $summary = (new ContextSummaryProvider($repo))->summarize('2025-2026');

        self::assertStringContainsString('curso 2025-2026', $summary);
        self::assertStringContainsString('Debilidades:', $summary);
        self::assertStringContainsString('Financiación dependiente', $summary);
        self::assertStringContainsString('Amenazas:', $summary);
        self::assertStringContainsString('Fortalezas:', $summary);
        // The opportunities quadrant is null, so its heading must not appear.
        self::assertStringNotContainsString('Oportunidades:', $summary);
        // Nothing from the other course must leak in.
        self::assertStringNotContainsString('De otro curso', $summary);
    }

    public function testMatchesTheCourseWhenCalledWithASlashSeparator(): void
    {
        $repo = $this->createMock(DafoAnalysisRepository::class);
        // The persisted course always uses a hyphen (entity regex), but the public method must
        // still match when called with a slash variant of the same course.
        $repo->method('findAllOrdered')->willReturn([
            $this->analysis('2025-2026', 'Una debilidad', null, null, null),
        ]);

        $summary = (new ContextSummaryProvider($repo))->summarize('2025/2026');

        self::assertStringContainsString('Una debilidad', $summary);
    }

    public function testReturnsEmptyWhenNoAnalysisForTheCourse(): void
    {
        $repo = $this->createMock(DafoAnalysisRepository::class);
        $repo->method('findAllOrdered')->willReturn([
            $this->analysis('2023-2024', 'Una debilidad', null, null, null),
        ]);

        self::assertSame('', (new ContextSummaryProvider($repo))->summarize('2025-2026'));
    }

    public function testReturnsEmptyWhenTheAnalysisHasEveryQuadrantBlank(): void
    {
        $repo = $this->createMock(DafoAnalysisRepository::class);
        $repo->method('findAllOrdered')->willReturn([
            $this->analysis('2025-2026', null, '   ', null, null),
        ]);

        self::assertSame('', (new ContextSummaryProvider($repo))->summarize('2025-2026'));
    }
}
