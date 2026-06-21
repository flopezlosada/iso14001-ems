<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\Enum\SupplierCriterion;
use PHPUnit\Framework\TestCase;

/**
 * Domain invariants of {@see SupplierCriterion}: the approval status is derived from the
 * criterion (single source of truth), per PC.05 §5.6.
 */
final class SupplierCriterionTest extends TestCase
{
    public function testApprovedCriteriaDeriveApprovedStatus(): void
    {
        self::assertTrue(SupplierCriterion::CAPABLE->isApproved());
        self::assertTrue(SupplierCriterion::ON_TRIAL->isApproved());
        self::assertSame('Aprobado', SupplierCriterion::CAPABLE->statusLabel());
        self::assertSame('Aprobado', SupplierCriterion::ON_TRIAL->statusLabel());
    }

    public function testNotCapableDerivesNotApproved(): void
    {
        self::assertFalse(SupplierCriterion::NOT_CAPABLE->isApproved());
        self::assertSame('No aprobado', SupplierCriterion::NOT_CAPABLE->statusLabel());
    }

    public function testEachCriterionHasASpanishLabel(): void
    {
        self::assertSame('Capacitado', SupplierCriterion::CAPABLE->label());
        self::assertSame('A prueba', SupplierCriterion::ON_TRIAL->label());
        self::assertSame('No capacitado', SupplierCriterion::NOT_CAPABLE->label());
    }
}
