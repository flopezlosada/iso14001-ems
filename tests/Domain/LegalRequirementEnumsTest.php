<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\Entity\LegalRequirement;
use App\Enum\ComplianceStatus;
use App\Enum\LegalScope;
use PHPUnit\Framework\TestCase;

/**
 * Domain invariants for legal requirements: enum labels and the non-compliance helper that drives
 * escalation to a non-conformity.
 */
final class LegalRequirementEnumsTest extends TestCase
{
    public function testScopeLabels(): void
    {
        self::assertSame('Estatal', LegalScope::NATIONAL->label());
        self::assertSame('Autonómico', LegalScope::REGIONAL->label());
        self::assertSame('Local', LegalScope::LOCAL->label());
        self::assertSame('Comunitario', LegalScope::EUROPEAN->label());
    }

    public function testComplianceStatusLabels(): void
    {
        self::assertSame('Cumple', ComplianceStatus::COMPLIANT->label());
        self::assertSame('No cumple', ComplianceStatus::NON_COMPLIANT->label());
        self::assertSame('Pendiente de evaluar', ComplianceStatus::PENDING->label());
    }

    public function testIsNonCompliantReflectsTheStatus(): void
    {
        $requirement = new LegalRequirement();
        self::assertFalse($requirement->isNonCompliant());

        $requirement->setComplianceStatus(ComplianceStatus::NON_COMPLIANT);
        self::assertTrue($requirement->isNonCompliant());

        $requirement->setComplianceStatus(ComplianceStatus::COMPLIANT);
        self::assertFalse($requirement->isNonCompliant());
    }
}
