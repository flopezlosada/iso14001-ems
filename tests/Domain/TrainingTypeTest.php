<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\Enum\TrainingType;
use PHPUnit\Framework\TestCase;

/**
 * Domain invariants of {@see TrainingType}: stored values and Spanish labels.
 */
final class TrainingTypeTest extends TestCase
{
    public function testEachTypeHasASpanishLabel(): void
    {
        self::assertSame('Interna', TrainingType::INTERNAL->label());
        self::assertSame('Externa', TrainingType::EXTERNAL->label());
    }

    public function testEveryCaseHasANonEmptyLabel(): void
    {
        foreach (TrainingType::cases() as $type) {
            self::assertNotSame('', $type->label());
        }
    }
}
