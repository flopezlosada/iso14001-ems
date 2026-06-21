<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\AspectEvaluation;
use App\Entity\EnvironmentalAspect;
use App\Enum\AspectType;
use App\Enum\DirectAspectCategory;
use App\Enum\InfluenceLevel;
use App\Enum\ScoreLevel;
use App\Service\AspectSignificanceCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The significance rule (PG-06.01 Anexo I) is the most error-prone part of the module, so it is
 * tested in isolation: the per-category sum, the discharge exception, the missing-intensity
 * default and the threshold boundary.
 */
final class AspectSignificanceCalculatorTest extends TestCase
{
    private function evaluation(DirectAspectCategory $category): AspectEvaluation
    {
        $aspect = (new EnvironmentalAspect())->setCategory($category);

        return (new AspectEvaluation())->setAspect($aspect);
    }

    public function testDirectAspectSumsTheThreeCriteria(): void
    {
        $calculator = new AspectSignificanceCalculator(10);
        $evaluation = $this->evaluation(DirectAspectCategory::CONSUMPTION)
            ->setFrequency(ScoreLevel::HIGH)
            ->setIntensity(ScoreLevel::HIGH)
            ->setHazard(ScoreLevel::HIGH);

        $calculator->apply($evaluation);

        self::assertSame(18, $evaluation->getSignificanceScore());
        self::assertTrue($evaluation->isSignificant());
    }

    public function testDischargeIgnoresIntensity(): void
    {
        $calculator = new AspectSignificanceCalculator(10);
        $evaluation = $this->evaluation(DirectAspectCategory::DISCHARGE)
            ->setFrequency(ScoreLevel::HIGH)
            ->setIntensity(ScoreLevel::HIGH) // must be ignored for discharges
            ->setHazard(ScoreLevel::HIGH);

        $calculator->apply($evaluation);

        // 6 (frequency) + 6 (hazard); intensity not added.
        self::assertSame(12, $evaluation->getSignificanceScore());
        self::assertTrue($evaluation->isSignificant());
    }

    public function testMissingIntensityDefaultsToFour(): void
    {
        $calculator = new AspectSignificanceCalculator(10);
        $evaluation = $this->evaluation(DirectAspectCategory::WASTE)
            ->setFrequency(ScoreLevel::LOW)   // 2
            ->setHazard(ScoreLevel::LOW);      // 2
        // intensity left null -> counts as 4 (no prior-year data)

        $calculator->apply($evaluation);

        self::assertSame(8, $evaluation->getSignificanceScore());
        self::assertFalse($evaluation->isSignificant());
    }

    /**
     * The significance threshold is NOT a single value: each direct category has its own
     * (PG-06.01 Anexo I, "Límite de significancia"). A score equal to the threshold is not
     * significant; one above it is. The injected threshold (10) must NOT be used for direct
     * aspects — these cases would misclassify if it were.
     *
     * @return iterable<string, array{DirectAspectCategory, ScoreLevel, ScoreLevel|null, ScoreLevel, int, bool}>
     */
    public static function categoryThresholdProvider(): iterable
    {
        // category, frequency, intensity (null for discharge), hazard, expected score, expected significant
        yield 'consumos: 12 (= threshold) not significant' => [DirectAspectCategory::CONSUMPTION, ScoreLevel::MEDIUM, ScoreLevel::MEDIUM, ScoreLevel::MEDIUM, 12, false];
        yield 'consumos: 14 (> threshold) significant' => [DirectAspectCategory::CONSUMPTION, ScoreLevel::MEDIUM, ScoreLevel::HIGH, ScoreLevel::MEDIUM, 14, true];
        yield 'emisiones: 12 (= threshold) not significant' => [DirectAspectCategory::EMISSION, ScoreLevel::MEDIUM, ScoreLevel::MEDIUM, ScoreLevel::MEDIUM, 12, false];
        yield 'emisiones: 16 (> threshold) significant' => [DirectAspectCategory::EMISSION, ScoreLevel::HIGH, ScoreLevel::MEDIUM, ScoreLevel::HIGH, 16, true];
        yield 'residuos: 10 (= threshold) not significant' => [DirectAspectCategory::WASTE, ScoreLevel::MEDIUM, ScoreLevel::MEDIUM, ScoreLevel::LOW, 10, false];
        yield 'residuos: 12 (> threshold) significant' => [DirectAspectCategory::WASTE, ScoreLevel::MEDIUM, ScoreLevel::MEDIUM, ScoreLevel::MEDIUM, 12, true];
        yield 'vertidos: 8 (= threshold) not significant' => [DirectAspectCategory::DISCHARGE, ScoreLevel::LOW, null, ScoreLevel::HIGH, 8, false];
        yield 'vertidos: 10 (> threshold) significant' => [DirectAspectCategory::DISCHARGE, ScoreLevel::MEDIUM, null, ScoreLevel::HIGH, 10, true];
    }

    #[DataProvider('categoryThresholdProvider')]
    public function testThresholdIsPerCategory(DirectAspectCategory $category, ScoreLevel $frequency, ?ScoreLevel $intensity, ScoreLevel $hazard, int $expectedScore, bool $expectedSignificant): void
    {
        $calculator = new AspectSignificanceCalculator(10);
        $evaluation = $this->evaluation($category)
            ->setFrequency($frequency)
            ->setIntensity($intensity)
            ->setHazard($hazard);

        $calculator->apply($evaluation);

        self::assertSame($expectedScore, $evaluation->getSignificanceScore());
        self::assertSame($expectedSignificant, $evaluation->isSignificant());
    }

    public function testDischargeScoreOfTenIsSignificant(): void
    {
        // Regression: a discharge scoring 10 (vertido semanal=4 + sustancias peligrosas=6) is
        // significant (threshold 8). With a single threshold of 10 it was wrongly marked NOT
        // significant — a false negative an ISO auditor would flag against Anexo I.
        $calculator = new AspectSignificanceCalculator(10);
        $evaluation = $this->evaluation(DirectAspectCategory::DISCHARGE)
            ->setFrequency(ScoreLevel::MEDIUM)
            ->setHazard(ScoreLevel::HIGH);

        $calculator->apply($evaluation);

        self::assertSame(10, $evaluation->getSignificanceScore());
        self::assertTrue($evaluation->isSignificant());
    }

    public function testInjectedThresholdAppliesToAbnormalAspects(): void
    {
        // Abnormal aspects (Anexo III) DO use the injected threshold; direct aspects do not.
        $aspect = (new EnvironmentalAspect())->setType(AspectType::ABNORMAL);
        $evaluation = (new AspectEvaluation())->setAspect($aspect)
            ->setProbability(ScoreLevel::LOW)
            ->setControl(ScoreLevel::LOW)
            ->setSeverity(ScoreLevel::MEDIUM); // 2 + 2 + 4 = 8

        (new AspectSignificanceCalculator(10))->apply($evaluation);
        self::assertFalse($evaluation->isSignificant());

        (new AspectSignificanceCalculator(6))->apply($evaluation);
        self::assertTrue($evaluation->isSignificant());
    }

    public function testDirectAspectWithoutCategoryFallsBackToInjectedThreshold(): void
    {
        // A direct aspect with no category has no per-category threshold, so it falls back to the
        // injected default (10 here): score 12 > 10 → significant.
        $aspect = (new EnvironmentalAspect())->setType(AspectType::DIRECT); // category left null
        $evaluation = (new AspectEvaluation())->setAspect($aspect)
            ->setFrequency(ScoreLevel::MEDIUM)
            ->setIntensity(ScoreLevel::MEDIUM)
            ->setHazard(ScoreLevel::MEDIUM); // 12

        (new AspectSignificanceCalculator(10))->apply($evaluation);

        self::assertSame(12, $evaluation->getSignificanceScore());
        self::assertTrue($evaluation->isSignificant());
    }

    public function testAbnormalAspectSumsProbabilityControlSeverity(): void
    {
        $aspect = (new EnvironmentalAspect())->setType(AspectType::ABNORMAL);
        $evaluation = (new AspectEvaluation())->setAspect($aspect)
            ->setProbability(ScoreLevel::HIGH)   // 6
            ->setControl(ScoreLevel::MEDIUM)     // 4
            ->setSeverity(ScoreLevel::HIGH);     // 6

        (new AspectSignificanceCalculator(10))->apply($evaluation);

        self::assertSame(16, $evaluation->getSignificanceScore());
        self::assertTrue($evaluation->isSignificant());
    }

    public function testIndirectAspectRecordsInfluenceAndKeepsManualSignificance(): void
    {
        $aspect = (new EnvironmentalAspect())->setType(AspectType::INDIRECT);
        $evaluation = (new AspectEvaluation())->setAspect($aspect)
            ->setInfluence(InfluenceLevel::HIGH)
            ->setSignificant(true); // manual decision (no threshold defined for indirect)

        (new AspectSignificanceCalculator(10))->apply($evaluation);

        // Score reflects the influence; the manual significant flag is preserved (not overwritten).
        self::assertSame(3, $evaluation->getSignificanceScore());
        self::assertTrue($evaluation->isSignificant());
    }
}
