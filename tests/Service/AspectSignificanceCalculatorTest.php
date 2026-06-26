<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\AspectEvaluation;
use App\Entity\EnvironmentalAspect;
use App\Entity\Settings;
use App\Enum\AspectType;
use App\Enum\DirectAspectCategory;
use App\Enum\InfluenceLevel;
use App\Enum\ScoreLevel;
use App\Repository\SettingsRepository;
use App\Service\AspectSignificanceCalculator;
use App\Service\SettingsProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The significance rule (PG-06.01 Anexo I) is the most error-prone part of the module, so it is
 * tested in isolation: the per-category sum, intensity for every category (discharges included),
 * the missing-intensity default and — crucially — the threshold boundary, which is INCLUSIVE
 * (score == threshold IS significant), as the certified registry RG-06.01.01 Rev 02 shows.
 */
final class AspectSignificanceCalculatorTest extends TestCase
{
    private function evaluation(DirectAspectCategory $category): AspectEvaluation
    {
        $aspect = (new EnvironmentalAspect())->setCategory($category);

        return (new AspectEvaluation())->setAspect($aspect);
    }

    /**
     * Calculator backed by a Settings with the default per-category thresholds (12/12/10/12) and the
     * given abnormal/fallback threshold.
     */
    private function calculator(int $abnormalThreshold = 10): AspectSignificanceCalculator
    {
        $settings = (new Settings())->setAbnormalThreshold($abnormalThreshold);
        $repository = $this->createMock(SettingsRepository::class);
        $repository->method('findSettings')->willReturn($settings);

        return new AspectSignificanceCalculator(new SettingsProvider($repository));
    }

    public function testDirectAspectSumsTheThreeCriteria(): void
    {
        $calculator = $this->calculator();
        $evaluation = $this->evaluation(DirectAspectCategory::CONSUMPTION)
            ->setFrequency(ScoreLevel::HIGH)
            ->setIntensity(ScoreLevel::HIGH)
            ->setHazard(ScoreLevel::HIGH);

        $calculator->apply($evaluation);

        self::assertSame(18, $evaluation->getSignificanceScore());
        self::assertTrue($evaluation->isSignificant());
    }

    public function testDischargeNowUsesIntensity(): void
    {
        // RG-06.01.01 Rev 02 added the intensity criterion to discharges (vertidos): the score is
        // now frequency + intensity + hazard, like every other direct category.
        $calculator = $this->calculator();
        $evaluation = $this->evaluation(DirectAspectCategory::DISCHARGE)
            ->setFrequency(ScoreLevel::HIGH)    // 6
            ->setIntensity(ScoreLevel::HIGH)    // 6 (must now be added)
            ->setHazard(ScoreLevel::HIGH);      // 6

        $calculator->apply($evaluation);

        self::assertSame(18, $evaluation->getSignificanceScore());
        self::assertTrue($evaluation->isSignificant());
    }

    public function testMissingIntensityDefaultsToFour(): void
    {
        $calculator = $this->calculator();
        $evaluation = $this->evaluation(DirectAspectCategory::WASTE)
            ->setFrequency(ScoreLevel::LOW)   // 2
            ->setHazard(ScoreLevel::LOW);      // 2
        // intensity left null -> counts as 4 (no prior-year data)

        $calculator->apply($evaluation);

        self::assertSame(8, $evaluation->getSignificanceScore());
        self::assertFalse($evaluation->isSignificant());
    }

    public function testMissingIntensityDefaultsToFourForDischarges(): void
    {
        // The no-prior-year-data default (4) now applies to discharges too, since they carry intensity.
        $calculator = $this->calculator();
        $evaluation = $this->evaluation(DirectAspectCategory::DISCHARGE)
            ->setFrequency(ScoreLevel::LOW)   // 2
            ->setHazard(ScoreLevel::HIGH);     // 6
        // intensity left null -> counts as 4

        $calculator->apply($evaluation);

        self::assertSame(12, $evaluation->getSignificanceScore());
        self::assertTrue($evaluation->isSignificant()); // 12 >= discharge threshold 12
    }

    /**
     * The significance threshold is NOT a single value: each direct category has its own
     * (PG-06.01 Anexo I, "Límite de significancia"). The boundary is INCLUSIVE: a score EQUAL to
     * the threshold IS significant; only a score strictly below it is not. The injected threshold
     * (10) must NOT be used for direct aspects — these cases would misclassify if it were.
     *
     * @return iterable<string, array{DirectAspectCategory, ScoreLevel, ScoreLevel|null, ScoreLevel, int, bool}>
     */
    public static function categoryThresholdProvider(): iterable
    {
        // category, frequency, intensity, hazard, expected score, expected significant
        yield 'consumos: 10 (< threshold 12) not significant' => [DirectAspectCategory::CONSUMPTION, ScoreLevel::MEDIUM, ScoreLevel::MEDIUM, ScoreLevel::LOW, 10, false];
        yield 'consumos: 12 (= threshold) significant' => [DirectAspectCategory::CONSUMPTION, ScoreLevel::MEDIUM, ScoreLevel::MEDIUM, ScoreLevel::MEDIUM, 12, true];
        yield 'emisiones: 10 (< threshold 12) not significant' => [DirectAspectCategory::EMISSION, ScoreLevel::MEDIUM, ScoreLevel::MEDIUM, ScoreLevel::LOW, 10, false];
        yield 'emisiones: 12 (= threshold) significant' => [DirectAspectCategory::EMISSION, ScoreLevel::MEDIUM, ScoreLevel::MEDIUM, ScoreLevel::MEDIUM, 12, true];
        yield 'residuos: 8 (< threshold 10) not significant' => [DirectAspectCategory::WASTE, ScoreLevel::LOW, ScoreLevel::MEDIUM, ScoreLevel::LOW, 8, false];
        yield 'residuos: 10 (= threshold) significant' => [DirectAspectCategory::WASTE, ScoreLevel::MEDIUM, ScoreLevel::MEDIUM, ScoreLevel::LOW, 10, true];
        yield 'vertidos: 10 (< threshold 12) not significant' => [DirectAspectCategory::DISCHARGE, ScoreLevel::LOW, ScoreLevel::LOW, ScoreLevel::HIGH, 10, false];
        yield 'vertidos: 12 (= threshold) significant' => [DirectAspectCategory::DISCHARGE, ScoreLevel::LOW, ScoreLevel::MEDIUM, ScoreLevel::HIGH, 12, true];
    }

    #[DataProvider('categoryThresholdProvider')]
    public function testThresholdIsPerCategoryAndInclusive(DirectAspectCategory $category, ScoreLevel $frequency, ?ScoreLevel $intensity, ScoreLevel $hazard, int $expectedScore, bool $expectedSignificant): void
    {
        $calculator = $this->calculator();
        $evaluation = $this->evaluation($category)
            ->setFrequency($frequency)
            ->setIntensity($intensity)
            ->setHazard($hazard);

        $calculator->apply($evaluation);

        self::assertSame($expectedScore, $evaluation->getSignificanceScore());
        self::assertSame($expectedSignificant, $evaluation->isSignificant());
    }

    public function testWasteScoreEqualToThresholdIsSignificant(): void
    {
        // Real case from RG-06.01.01 Rev 02 ("Restos de tóner"): frequency 2 + intensity 4 +
        // hazard 4 = 10 = waste threshold 10. The certified sheet marks it SIGNIFICATIVO; with the
        // former strict ">" the app wrongly returned NOT significant — a false negative an ISO
        // auditor would flag against Anexo I.
        $calculator = $this->calculator();
        $evaluation = $this->evaluation(DirectAspectCategory::WASTE)
            ->setFrequency(ScoreLevel::LOW)      // 2
            ->setIntensity(ScoreLevel::MEDIUM)   // 4
            ->setHazard(ScoreLevel::MEDIUM);     // 4

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

        ($this->calculator())->apply($evaluation);
        self::assertFalse($evaluation->isSignificant()); // 8 < 10

        $this->calculator(8)->apply($evaluation);
        self::assertTrue($evaluation->isSignificant()); // 8 >= 8 (inclusive boundary)
    }

    public function testDirectAspectWithoutCategoryFallsBackToInjectedThreshold(): void
    {
        // A direct aspect with no category has no per-category threshold, so it falls back to the
        // injected default (10 here): score 10 >= 10 -> significant (inclusive boundary).
        $aspect = (new EnvironmentalAspect())->setType(AspectType::DIRECT); // category left null
        $evaluation = (new AspectEvaluation())->setAspect($aspect)
            ->setFrequency(ScoreLevel::MEDIUM)
            ->setIntensity(ScoreLevel::MEDIUM)
            ->setHazard(ScoreLevel::LOW); // 10

        ($this->calculator())->apply($evaluation);

        self::assertSame(10, $evaluation->getSignificanceScore());
        self::assertTrue($evaluation->isSignificant());
    }

    public function testAbnormalAspectSumsProbabilityControlSeverity(): void
    {
        $aspect = (new EnvironmentalAspect())->setType(AspectType::ABNORMAL);
        $evaluation = (new AspectEvaluation())->setAspect($aspect)
            ->setProbability(ScoreLevel::HIGH)   // 6
            ->setControl(ScoreLevel::MEDIUM)     // 4
            ->setSeverity(ScoreLevel::HIGH);     // 6

        ($this->calculator())->apply($evaluation);

        self::assertSame(16, $evaluation->getSignificanceScore());
        self::assertTrue($evaluation->isSignificant());
    }

    public function testIndirectAspectRecordsInfluenceAndKeepsManualSignificance(): void
    {
        $aspect = (new EnvironmentalAspect())->setType(AspectType::INDIRECT);
        $evaluation = (new AspectEvaluation())->setAspect($aspect)
            ->setInfluence(InfluenceLevel::HIGH)
            ->setSignificant(true); // manual decision (no threshold defined for indirect)

        ($this->calculator())->apply($evaluation);

        // Score reflects the influence; the manual significant flag is preserved (not overwritten).
        self::assertSame(3, $evaluation->getSignificanceScore());
        self::assertTrue($evaluation->isSignificant());
    }

    public function testIndirectAspectWithNullInfluenceScoresZero(): void
    {
        $aspect = (new EnvironmentalAspect())->setType(AspectType::INDIRECT);
        $evaluation = (new AspectEvaluation())->setAspect($aspect); // influence left null

        ($this->calculator())->apply($evaluation);

        self::assertSame(0, $evaluation->getSignificanceScore());
    }

    public function testIndirectAspectWithInfluenceNoneScoresZero(): void
    {
        // RG-06.01.01 Rev 02 added an explicit "Nula" influence level (value 0), distinct from an
        // unevaluated (null) one but scoring the same 0.
        $aspect = (new EnvironmentalAspect())->setType(AspectType::INDIRECT);
        $evaluation = (new AspectEvaluation())->setAspect($aspect)
            ->setInfluence(InfluenceLevel::NONE);

        ($this->calculator())->apply($evaluation);

        // NONE backs 0, so the recorded significance is 0 — the same as a null (unevaluated) influence.
        self::assertSame(0, $evaluation->getSignificanceScore());
    }
}
