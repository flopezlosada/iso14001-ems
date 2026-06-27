<?php

declare(strict_types=1);

namespace App\Tests\Util;

use App\Util\SchoolYear;
use PHPUnit\Framework\TestCase;

final class SchoolYearTest extends TestCase
{
    /**
     * @dataProvider currentCases
     */
    public function testCurrent(string $date, string $expected): void
    {
        self::assertSame($expected, SchoolYear::current(new \DateTimeImmutable($date)));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function currentCases(): iterable
    {
        yield 'mid-course winter' => ['2026-02-01', '2025-2026'];
        yield 'autumn after start' => ['2025-10-01', '2025-2026'];
        yield 'first day of course' => ['2026-09-01', '2026-2027'];
        yield 'last day before course' => ['2026-08-31', '2025-2026'];
    }

    public function testNext(): void
    {
        self::assertSame('2026-2027', SchoolYear::next('2025-2026'));
    }

    public function testNextNormalisesSlashSeparatedValues(): void
    {
        // The historic risk fixtures stored "2025/2026"; next() still normalises to canonical dashes.
        self::assertSame('2026-2027', SchoolYear::next('2025/2026'));
    }

    public function testPrevious(): void
    {
        self::assertSame('2024-2025', SchoolYear::previous('2025-2026'));
    }

    public function testPreviousNormalisesSlashSeparatedValues(): void
    {
        self::assertSame('2024-2025', SchoolYear::previous('2025/2026'));
    }
}
