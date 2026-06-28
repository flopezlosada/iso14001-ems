<?php

declare(strict_types=1);

namespace App\Tests\Enum;

use App\Enum\ReviewSectionGroup;
use App\Enum\ReviewSectionKey;
use PHPUnit\Framework\TestCase;

final class ReviewSectionKeyTest extends TestCase
{
    public function testOutputSectionsHaveClosedVerdictOptions(): void
    {
        self::assertSame(
            ['Conveniente, adecuado y eficaz', 'Adecuado con mejoras', 'Requiere cambios importantes'],
            ReviewSectionKey::CONCLUSIONS->decisionOptions(),
        );

        foreach (ReviewSectionKey::cases() as $key) {
            if (ReviewSectionGroup::OUTPUT === $key->group()) {
                self::assertNotEmpty($key->decisionOptions(), sprintf('%s is a decision and must offer verdicts', $key->value));
            }
        }
    }

    public function testInputSectionsHaveNoVerdictOptions(): void
    {
        foreach (ReviewSectionKey::cases() as $key) {
            if (ReviewSectionGroup::INPUT === $key->group()) {
                self::assertSame([], $key->decisionOptions(), sprintf('%s is an input and must not offer verdicts', $key->value));
            }
        }
    }
}
