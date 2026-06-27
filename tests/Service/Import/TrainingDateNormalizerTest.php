<?php

declare(strict_types=1);

namespace App\Tests\Service\Import;

use App\Service\Import\TrainingDateNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the F.03.0 date normalization convention (the only domain rule of the training
 * import): the exact mapping of the sheet's free-text dates onto a calendar date, and the cases
 * that must stay non-normalizable (returning null) so the importer quarantines them.
 */
final class TrainingDateNormalizerTest extends TestCase
{
    private TrainingDateNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new TrainingDateNormalizer();
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function normalizable(): iterable
    {
        yield 'ISO date (from Excel serial)' => ['2023-10-30', '2023-10-30'];
        yield 'full date dd/mm/yyyy' => ['30/10/2023', '2023-10-30'];
        yield 'full date dd.mm.yyyy' => ['30.10.2023', '2023-10-30'];
        yield 'full date dd-mm-yy' => ['30-10-23', '2023-10-30'];
        yield 'month name + year -> first of month' => ['octubre 2023', '2023-10-01'];
        yield 'abbreviated month with dot + year' => ['oct. 2023', '2023-10-01'];
        yield 'month name capitalized + accents' => ['Septiembre 2023', '2023-09-01'];
        yield 'day range -> start day' => ['23 al 27/10/23', '2023-10-23'];
        yield 'day range 4-digit year' => ['23 al 27/10/2024', '2024-10-23'];
    }

    #[DataProvider('normalizable')]
    public function testNormalizesKnownShapes(string $raw, string $expected): void
    {
        $date = $this->normalizer->normalize($raw);

        self::assertNotNull($date);
        self::assertSame($expected, $date->format('Y-m-d'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function nonNormalizable(): iterable
    {
        yield 'empty' => [''];
        yield 'blank' => ['   '];
        yield 'free text' => ['A la semana de su incorporación al centro'];
        yield 'planning note' => ['previsto según calendario por curso'];
        yield 'month without year' => ['oct.'];
        yield 'month name with 2-digit year' => ['octubre 23']; // 2-digit years only on numeric dates
        yield 'day + month without year' => ['30 Abril'];
        yield 'impossible calendar date' => ['31/02/2023'];
    }

    #[DataProvider('nonNormalizable')]
    public function testReturnsNullForNonNormalizableText(string $raw): void
    {
        self::assertNull($this->normalizer->normalize($raw));
    }
}
