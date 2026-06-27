<?php

declare(strict_types=1);

namespace App\Tests\Util;

use App\Util\CopyForward;
use PHPUnit\Framework\TestCase;

final class CopyForwardTest extends TestCase
{
    /**
     * Identity key for the string rows used in these tests.
     */
    private function key(): callable
    {
        return static fn (string $s): string => mb_strtolower(trim($s));
    }

    public function testEmptyDestinationBringsAllCandidates(): void
    {
        $missing = CopyForward::missing(['Agua', 'Energía'], [], $this->key());

        self::assertSame(['Agua', 'Energía'], $missing);
    }

    public function testOnlyMissingCandidatesAreReturnedPreservingOrder(): void
    {
        $missing = CopyForward::missing(
            ['Agua', 'Energía', 'Residuos'],
            ['Energía'],
            $this->key(),
        );

        self::assertSame(['Agua', 'Residuos'], $missing);
    }

    public function testKeyComparisonHonoursNormalisation(): void
    {
        // Destination has "  agua " which normalises to the same key as the candidate "Agua".
        $missing = CopyForward::missing(['Agua', 'Energía'], ['  agua '], $this->key());

        self::assertSame(['Energía'], $missing);
    }

    public function testNothingNewWhenAllPresent(): void
    {
        $missing = CopyForward::missing(['Agua'], ['agua', 'energía'], $this->key());

        self::assertSame([], $missing);
    }
}
