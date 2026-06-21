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

    public function testSignificanceUsesStrictlyGreaterThanThreshold(): void
    {
        $calculator = new AspectSignificanceCalculator(10);

        // Exactly at the threshold (4 + 4 default + 2 = 10) is NOT significant.
        $atThreshold = $this->evaluation(DirectAspectCategory::CONSUMPTION)
            ->setFrequency(ScoreLevel::MEDIUM)
            ->setHazard(ScoreLevel::LOW);
        $calculator->apply($atThreshold);
        self::assertSame(10, $atThreshold->getSignificanceScore());
        self::assertFalse($atThreshold->isSignificant());

        // One above the threshold is significant.
        $aboveThreshold = $this->evaluation(DirectAspectCategory::CONSUMPTION)
            ->setFrequency(ScoreLevel::MEDIUM)
            ->setIntensity(ScoreLevel::MEDIUM)
            ->setHazard(ScoreLevel::MEDIUM);
        $calculator->apply($aboveThreshold);
        self::assertSame(12, $aboveThreshold->getSignificanceScore());
        self::assertTrue($aboveThreshold->isSignificant());
    }

    public function testThresholdIsConfigurable(): void
    {
        // With a lower threshold, the same score becomes significant.
        $evaluation = $this->evaluation(DirectAspectCategory::EMISSION)
            ->setFrequency(ScoreLevel::LOW)
            ->setIntensity(ScoreLevel::LOW)
            ->setHazard(ScoreLevel::MEDIUM); // 2 + 2 + 4 = 8

        (new AspectSignificanceCalculator(10))->apply($evaluation);
        self::assertFalse($evaluation->isSignificant());

        (new AspectSignificanceCalculator(6))->apply($evaluation);
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
