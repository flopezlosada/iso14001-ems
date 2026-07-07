<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\Entity\Supplier;
use App\Entity\SupplierEvaluation;
use App\Enum\SupplierCriterion;
use PHPUnit\Framework\TestCase;

/**
 * Domain invariant of {@see Supplier::evaluationFor()}: the control list shows the standing for the
 * period in course, not just the latest evaluation on record.
 */
final class SupplierEvaluationLookupTest extends TestCase
{
    public function testReturnsTheEvaluationOfTheRequestedYear(): void
    {
        $supplier = new Supplier();
        $supplier->addEvaluation($this->evaluation(2025, SupplierCriterion::CAPABLE));
        $supplier->addEvaluation($this->evaluation(2026, SupplierCriterion::ON_TRIAL));

        self::assertSame(SupplierCriterion::ON_TRIAL, $supplier->evaluationFor(2026)?->getCriterion());
        self::assertSame(SupplierCriterion::CAPABLE, $supplier->evaluationFor(2025)?->getCriterion());
    }

    public function testReturnsNullWhenNotEvaluatedThatYear(): void
    {
        // Approved last year but not yet evaluated this year → pending for the period in course.
        $supplier = new Supplier();
        $supplier->addEvaluation($this->evaluation(2025, SupplierCriterion::CAPABLE));

        self::assertNull($supplier->evaluationFor(2026));
    }

    private function evaluation(int $year, SupplierCriterion $criterion): SupplierEvaluation
    {
        return (new SupplierEvaluation())->setYear($year)->setCriterion($criterion);
    }
}
