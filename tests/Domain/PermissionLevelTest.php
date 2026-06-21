<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\Enum\PermissionLevel;
use PHPUnit\Framework\TestCase;

/**
 * Domain invariant: WRITE includes READ; lower levels do not satisfy higher ones.
 */
final class PermissionLevelTest extends TestCase
{
    public function testWriteSatisfiesReadAndWrite(): void
    {
        self::assertTrue(PermissionLevel::WRITE->satisfies(PermissionLevel::READ));
        self::assertTrue(PermissionLevel::WRITE->satisfies(PermissionLevel::WRITE));
    }

    public function testReadSatisfiesReadButNotWrite(): void
    {
        self::assertTrue(PermissionLevel::READ->satisfies(PermissionLevel::READ));
        self::assertFalse(PermissionLevel::READ->satisfies(PermissionLevel::WRITE));
    }

    public function testNoneSatisfiesNothingButNone(): void
    {
        self::assertFalse(PermissionLevel::NONE->satisfies(PermissionLevel::READ));
        self::assertTrue(PermissionLevel::NONE->satisfies(PermissionLevel::NONE));
    }
}
