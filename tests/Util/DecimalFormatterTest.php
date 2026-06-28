<?php

declare(strict_types=1);

namespace App\Tests\Util;

use App\Util\DecimalFormatter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DecimalFormatterTest extends TestCase
{
    #[DataProvider('displayCases')]
    public function testDisplay(string|int|float|null $value, string $expected): void
    {
        self::assertSame($expected, DecimalFormatter::display($value));
    }

    /**
     * @return iterable<string, array{string|int|float|null, string}>
     */
    public static function displayCases(): iterable
    {
        // The bug: an integer stored at scale 3 looked like a thousands figure.
        yield 'integer padded to scale' => ['150.000', '150'];
        // The integer part must survive even when it ends in zeros (the rtrim trap).
        yield 'integer ending in zeros' => ['100.000', '100'];
        yield 'real decimal keeps its digits' => ['1.500', '1.5'];
        yield 'real decimal with three digits' => ['1.234', '1.234'];
        yield 'zero' => ['0.000', '0'];
        yield 'negative decimal' => ['-5.500', '-5.5'];
        yield 'large value, no thousands separator' => ['12000.000', '12000'];
        yield 'empty string' => ['', ''];
        yield 'null' => [null, ''];
        yield 'plain integer input' => [150, '150'];
        yield 'float input' => [1.5, '1.5'];
    }
}
