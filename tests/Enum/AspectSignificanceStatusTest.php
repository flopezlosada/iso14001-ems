<?php

declare(strict_types=1);

namespace App\Tests\Enum;

use App\Entity\AspectEvaluation;
use App\Enum\AspectSignificanceStatus;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the aspect significance status: the resolution from an evaluation and the semantic
 * badge scale (only a significant aspect stands out; everything else stays muted).
 */
final class AspectSignificanceStatusTest extends TestCase
{
    public function testResolvesUnevaluatedWhenThereIsNoEvaluation(): void
    {
        self::assertSame(
            AspectSignificanceStatus::UNEVALUATED,
            AspectSignificanceStatus::forEvaluation(null),
        );
    }

    public function testResolvesSignificantAndNotSignificantFromTheFlag(): void
    {
        self::assertSame(
            AspectSignificanceStatus::SIGNIFICANT,
            AspectSignificanceStatus::forEvaluation((new AspectEvaluation())->setSignificant(true)),
        );
        self::assertSame(
            AspectSignificanceStatus::NOT_SIGNIFICANT,
            AspectSignificanceStatus::forEvaluation((new AspectEvaluation())->setSignificant(false)),
        );
    }

    public function testOnlySignificantStandsOutOnTheScale(): void
    {
        self::assertSame('badge--warning', AspectSignificanceStatus::SIGNIFICANT->badgeClass());
        self::assertSame('badge--draft', AspectSignificanceStatus::UNEVALUATED->badgeClass());
        self::assertSame('badge--done', AspectSignificanceStatus::NOT_SIGNIFICANT->badgeClass());
    }
}
