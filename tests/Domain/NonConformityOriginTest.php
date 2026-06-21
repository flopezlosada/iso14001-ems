<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\Enum\NonConformityOrigin;
use PHPUnit\Framework\TestCase;

/**
 * Domain invariants of {@see NonConformityOrigin}: the reference codes must stay stable, since
 * business identifiers (NC.<code>.<year>.<NN>) depend on them.
 */
final class NonConformityOriginTest extends TestCase
{
    public function testEachOriginHasItsReferenceCode(): void
    {
        self::assertSame('I', NonConformityOrigin::INTERNAL->code());
        self::assertSame('AI', NonConformityOrigin::INTERNAL_AUDIT->code());
        self::assertSame('AE', NonConformityOrigin::EXTERNAL_AUDIT->code());
    }

    public function testEachOriginHasASpanishLabel(): void
    {
        self::assertSame('Interna', NonConformityOrigin::INTERNAL->label());
        self::assertSame('Auditoría interna', NonConformityOrigin::INTERNAL_AUDIT->label());
        self::assertSame('Auditoría externa', NonConformityOrigin::EXTERNAL_AUDIT->label());
    }
}
