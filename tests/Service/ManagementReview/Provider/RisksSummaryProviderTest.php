<?php

declare(strict_types=1);

namespace App\Tests\Service\ManagementReview\Provider;

use App\Entity\RiskAssessment;
use App\Entity\RiskOpportunity;
use App\Enum\RiskCategory;
use App\Enum\RiskLevel;
use App\Enum\RiskOpportunityType;
use App\Repository\RiskOpportunityRepository;
use App\Service\ManagementReview\Provider\RisksSummaryProvider;
use PHPUnit\Framework\TestCase;

final class RisksSummaryProviderTest extends TestCase
{
    /**
     * Builds an in-memory risk/opportunity with one valuation for the given course.
     */
    private function item(RiskOpportunityType $type, string $description, string $exercise, RiskCategory $category, int $score): RiskOpportunity
    {
        $item = (new RiskOpportunity())->setType($type)->setDescription($description);
        $assessment = (new RiskAssessment())
            ->setRiskOpportunity($item)
            ->setExercise($exercise)
            ->setProbability(RiskLevel::HIGH)
            ->setImpact(RiskLevel::HIGH)
            ->setScore($score)
            ->setCategory($category);
        $item->addAssessment($assessment);

        return $item;
    }

    public function testSummarisesValuationsMatchingTheCourseDespiteSeparator(): void
    {
        $repo = $this->createMock(RiskOpportunityRepository::class);
        $repo->method('findAllOrdered')->willReturn([
            // Seeded with a slash, the real validation uses a hyphen — must still match.
            $this->item(RiskOpportunityType::RISK, 'Sanción por residuos', '2025/2026', RiskCategory::CRITICAL, 9),
            $this->item(RiskOpportunityType::OPPORTUNITY, 'Ahorro energético', '2025-2026', RiskCategory::MODERATE, 4),
        ]);

        $summary = (new RisksSummaryProvider($repo))->summarize('2025-2026');

        self::assertStringContainsString('1 riesgos y 1 oportunidades', $summary);
        self::assertStringContainsString('Sanción por residuos', $summary);
        self::assertStringContainsString('Ahorro energético', $summary);
    }

    public function testReturnsEmptyWhenNoValuationForTheCourse(): void
    {
        $repo = $this->createMock(RiskOpportunityRepository::class);
        $repo->method('findAllOrdered')->willReturn([
            $this->item(RiskOpportunityType::RISK, 'Riesgo de otro curso', '2023-2024', RiskCategory::CRITICAL, 9),
        ]);

        self::assertSame('', (new RisksSummaryProvider($repo))->summarize('2025-2026'));
    }
}
