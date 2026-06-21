<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\Enum\MeasurementPeriodicity;
use App\Enum\SgmaProcess;
use PHPUnit\Framework\TestCase;

/**
 * Domain invariants for indicators: the Spanish labels of the process and periodicity enums.
 */
final class IndicatorEnumsTest extends TestCase
{
    public function testProcessLabels(): void
    {
        self::assertSame('Planificación', SgmaProcess::PLANNING->label());
        self::assertSame('Recursos', SgmaProcess::RESOURCES->label());
        self::assertSame('Evaluación del desempeño', SgmaProcess::PERFORMANCE_EVALUATION->label());
        self::assertSame('Mejora', SgmaProcess::IMPROVEMENT->label());
    }

    public function testPeriodicityLabels(): void
    {
        self::assertSame('Mensual', MeasurementPeriodicity::MONTHLY->label());
        self::assertSame('Anual', MeasurementPeriodicity::ANNUAL->label());
    }
}
