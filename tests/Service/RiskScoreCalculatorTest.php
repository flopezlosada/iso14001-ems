<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\RiskAssessment;
use App\Enum\RiskCategory;
use App\Enum\RiskLevel;
use App\Service\RiskScoreCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The valuation rule (PC.03.0 §5.2) is the only real domain logic of the module, so it is tested
 * in isolation: every possible product of the two 1-3 factors and the exact band boundaries.
 */
final class RiskScoreCalculatorTest extends TestCase
{
    /**
     * Every combination of probability (1-3) and impact (1-3): expected score and category.
     *
     * @return iterable<string, array{RiskLevel, RiskLevel, int, RiskCategory}>
     */
    public static function combinations(): iterable
    {
        yield '1×1 = 1 trivial' => [RiskLevel::LOW, RiskLevel::LOW, 1, RiskCategory::TRIVIAL];
        yield '1×2 = 2 trivial' => [RiskLevel::LOW, RiskLevel::MEDIUM, 2, RiskCategory::TRIVIAL];
        yield '2×1 = 2 trivial' => [RiskLevel::MEDIUM, RiskLevel::LOW, 2, RiskCategory::TRIVIAL];
        yield '1×3 = 3 moderate' => [RiskLevel::LOW, RiskLevel::HIGH, 3, RiskCategory::MODERATE];
        yield '3×1 = 3 moderate' => [RiskLevel::HIGH, RiskLevel::LOW, 3, RiskCategory::MODERATE];
        yield '2×2 = 4 moderate' => [RiskLevel::MEDIUM, RiskLevel::MEDIUM, 4, RiskCategory::MODERATE];
        yield '2×3 = 6 critical' => [RiskLevel::MEDIUM, RiskLevel::HIGH, 6, RiskCategory::CRITICAL];
        yield '3×2 = 6 critical' => [RiskLevel::HIGH, RiskLevel::MEDIUM, 6, RiskCategory::CRITICAL];
        yield '3×3 = 9 critical' => [RiskLevel::HIGH, RiskLevel::HIGH, 9, RiskCategory::CRITICAL];
    }

    #[DataProvider('combinations')]
    public function testApplyComputesScoreAndCategory(RiskLevel $probability, RiskLevel $impact, int $expectedScore, RiskCategory $expectedCategory): void
    {
        $assessment = (new RiskAssessment())
            ->setProbability($probability)
            ->setImpact($impact);

        (new RiskScoreCalculator())->apply($assessment);

        self::assertSame($expectedScore, $assessment->getScore());
        self::assertSame($expectedCategory, $assessment->getCategory());
    }

    /**
     * The band boundaries are the error-prone part: 2 is still trivial, 3 jumps to moderate, 4 is
     * the top of moderate and 6 is the bottom of critical (5, 7, 8 cannot occur with 1-3 factors).
     *
     * @return iterable<string, array{int, RiskCategory}>
     */
    public static function boundaries(): iterable
    {
        yield 'score 1 -> trivial' => [1, RiskCategory::TRIVIAL];
        yield 'score 2 -> trivial' => [2, RiskCategory::TRIVIAL];
        yield 'score 3 -> moderate' => [3, RiskCategory::MODERATE];
        yield 'score 4 -> moderate' => [4, RiskCategory::MODERATE];
        yield 'score 6 -> critical' => [6, RiskCategory::CRITICAL];
        yield 'score 9 -> critical' => [9, RiskCategory::CRITICAL];
    }

    #[DataProvider('boundaries')]
    public function testCategoryForBoundaries(int $score, RiskCategory $expected): void
    {
        self::assertSame($expected, (new RiskScoreCalculator())->categoryFor($score));
    }
}
