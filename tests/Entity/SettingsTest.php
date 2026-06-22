<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Settings;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * The Settings bounds are now enforced by validation (the former constructor guard of the intensity
 * estimator moved here), so this checks the constraints actually reject out-of-range values.
 */
final class SettingsTest extends TestCase
{
    private function validator(): ValidatorInterface
    {
        return Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
    }

    public function testDefaultsAreValid(): void
    {
        self::assertCount(0, $this->validator()->validate(new Settings()));
    }

    public function testRejectsBaselineYearsBelowOne(): void
    {
        $settings = (new Settings())->setIntensityBaselineYears(0);

        self::assertGreaterThan(0, $this->validator()->validate($settings)->count());
    }

    public function testRejectsRiseThresholdAboveOne(): void
    {
        $settings = (new Settings())->setIntensityRiseThreshold(1.5);

        self::assertGreaterThan(0, $this->validator()->validate($settings)->count());
    }
}
