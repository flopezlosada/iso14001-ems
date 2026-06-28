<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\LegalRequirement;
use App\Enum\EvaluationFrequency;
use PHPUnit\Framework\TestCase;

final class LegalRequirementTest extends TestCase
{
    public function testNextReviewIsDerivedFromLastReviewPlusCadence(): void
    {
        $requirement = (new LegalRequirement())
            ->setLastReviewedOn(new \DateTimeImmutable('2025-09-01'))
            ->setEvaluationFrequency(EvaluationFrequency::ANNUAL);

        self::assertEquals(new \DateTimeImmutable('2026-09-01'), $requirement->getNextReviewOn());
    }

    public function testEachCadenceAddsItsMonths(): void
    {
        $cases = [
            [EvaluationFrequency::MONTHLY, '2025-09-01', '2025-10-01'],
            [EvaluationFrequency::QUARTERLY, '2025-09-01', '2025-12-01'],
            [EvaluationFrequency::BIANNUAL, '2025-09-01', '2026-03-01'],
            [EvaluationFrequency::ANNUAL, '2025-09-01', '2026-09-01'],
        ];

        foreach ($cases as [$frequency, $last, $expected]) {
            $requirement = (new LegalRequirement())
                ->setLastReviewedOn(new \DateTimeImmutable($last))
                ->setEvaluationFrequency($frequency);

            self::assertEquals(new \DateTimeImmutable($expected), $requirement->getNextReviewOn(), $frequency->value);
        }
    }

    public function testOrderOfSettersDoesNotMatter(): void
    {
        $requirement = (new LegalRequirement())
            ->setEvaluationFrequency(EvaluationFrequency::QUARTERLY)
            ->setLastReviewedOn(new \DateTimeImmutable('2025-09-01'));

        self::assertEquals(new \DateTimeImmutable('2025-12-01'), $requirement->getNextReviewOn());
    }

    public function testNextReviewIsNullWhenAnyInputMissing(): void
    {
        $onlyDate = (new LegalRequirement())->setLastReviewedOn(new \DateTimeImmutable('2025-09-01'));
        self::assertNull($onlyDate->getNextReviewOn());

        $onlyFrequency = (new LegalRequirement())->setEvaluationFrequency(EvaluationFrequency::ANNUAL);
        self::assertNull($onlyFrequency->getNextReviewOn());
    }

    public function testExplicitSetterOverridesTheDerivedDateForTheEtl(): void
    {
        $requirement = (new LegalRequirement())
            ->setLastReviewedOn(new \DateTimeImmutable('2025-09-01'))
            ->setEvaluationFrequency(EvaluationFrequency::ANNUAL)
            ->setNextReviewOn(new \DateTimeImmutable('2026-05-15'));

        self::assertEquals(new \DateTimeImmutable('2026-05-15'), $requirement->getNextReviewOn());
    }
}
