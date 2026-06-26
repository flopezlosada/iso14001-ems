<?php

declare(strict_types=1);

namespace App\Tests\Service\ManagementReview;

use App\Service\ManagementReview\ExerciseYears;
use PHPUnit\Framework\TestCase;

final class ExerciseYearsTest extends TestCase
{
    public function testEndYearTakesTheClosingYear(): void
    {
        self::assertSame(2026, ExerciseYears::endYear('2025-2026'));
        self::assertSame(2024, ExerciseYears::endYear('2023-2024'));
    }
}
