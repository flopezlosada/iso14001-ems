<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Document;
use App\Entity\ScheduledAlert;
use App\Enum\AlertFrequency;
use App\Enum\Area;
use App\Enum\IsoChapter;
use App\Enum\ObligationStatus;
use App\Service\ObligationCalendar;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see ObligationCalendar}: the month-placement maths (which months an obligation
 * falls due, from its cadences), the start-month ordering, the urgency dot only on the imminent
 * occurrence, and that event-driven / not-applicable obligations are handled apart.
 */
final class ObligationCalendarTest extends TestCase
{
    private const TODAY = '2026-06-15';

    /**
     * @param list<array{0: AlertFrequency, 1: string}> $alerts pairs of [frequency, next-due date]
     */
    private function obligation(string $title, ?Area $area, array $alerts, ObligationStatus $status = ObligationStatus::PENDING, ?IsoChapter $chapter = null): Document
    {
        $document = new Document();
        $document->setTitle($title)->setStatus($status)->setLinkedArea($area)->setIsoChapter($chapter);
        foreach ($alerts as [$frequency, $nextDue]) {
            $alert = new ScheduledAlert();
            $alert->setFrequency($frequency)->setNextDueDate(new \DateTimeImmutable($nextDue));
            $document->addAlert($alert);
        }

        return $document;
    }

    /**
     * @param array<int, array{month: int, entries: list<array<string, mixed>>}> $months
     *
     * @return list<array<string, mixed>> the entries of the given month-of-year
     */
    private function entriesOf(array $months, int $month): array
    {
        foreach ($months as $cell) {
            if ($cell['month'] === $month) {
                return $cell['entries'];
            }
        }

        self::fail("No hay celda para el mes {$month}.");
    }

    /**
     * @param list<Document> $obligations
     *
     * @return array{months: list<array{month: int, entries: list<array<string, mixed>>}>, eventDriven: list<array<string, mixed>>}
     */
    private function build(array $obligations, int $startMonth = 1): array
    {
        return (new ObligationCalendar())->build($obligations, $startMonth, new \DateTimeImmutable(self::TODAY));
    }

    public function testMonthlyObligationAppearsInEveryMonth(): void
    {
        $result = $this->build([
            $this->obligation('Lectura mensual', Area::CONSUMPTION, [[AlertFrequency::MONTHLY, '2026-06-15']]),
        ]);

        self::assertCount(12, $result['months']);
        foreach ($result['months'] as $cell) {
            self::assertCount(1, $cell['entries'], "Falta en el mes {$cell['month']}");
        }
        self::assertSame([], $result['eventDriven']);
    }

    public function testAnnualObligationAppearsOnlyInItsMonth(): void
    {
        $result = $this->build([
            $this->obligation('Revisión anual', Area::MANAGEMENT_REVIEW, [[AlertFrequency::ANNUAL, '2026-03-10']]),
        ]);

        self::assertCount(1, $this->entriesOf($result['months'], 3));
        self::assertSame([], $this->entriesOf($result['months'], 4));
    }

    public function testBiannualObligationAppearsTwiceSixMonthsApart(): void
    {
        $result = $this->build([
            $this->obligation('Semestral', Area::TRAINING, [[AlertFrequency::BIANNUAL, '2026-02-01']]),
        ]);

        self::assertCount(1, $this->entriesOf($result['months'], 2));
        self::assertCount(1, $this->entriesOf($result['months'], 8));
        self::assertSame([], $this->entriesOf($result['months'], 5));
    }

    public function testEventDrivenObligationCarriesNoMonth(): void
    {
        $result = $this->build([
            $this->obligation('Por evento', Area::NONCONFORMITY, [[AlertFrequency::ON_EVENT, '2026-06-15']]),
        ]);

        self::assertCount(1, $result['eventDriven']);
        foreach ($result['months'] as $cell) {
            self::assertSame([], $cell['entries']);
        }
    }

    public function testNotApplicableObligationIsExcluded(): void
    {
        $result = $this->build([
            $this->obligation('No aplica', Area::WASTE, [[AlertFrequency::MONTHLY, '2026-06-15']], ObligationStatus::NOT_APPLICABLE),
        ]);

        foreach ($result['months'] as $cell) {
            self::assertSame([], $cell['entries']);
        }
        self::assertSame([], $result['eventDriven']);
    }

    public function testWindowStartsAtConfiguredMonth(): void
    {
        $result = $this->build([], 9);

        self::assertSame(9, $result['months'][0]['month']);
        self::assertSame(12, $result['months'][3]['month']);
        self::assertSame(8, $result['months'][11]['month']);
    }

    public function testUrgencyDotOnlyOnTheImminentOccurrence(): void
    {
        // Annual review whose only due date is in the past → overdue; its March cell carries the dot.
        $result = $this->build([
            $this->obligation('Vencida', Area::MANAGEMENT_REVIEW, [[AlertFrequency::ANNUAL, '2026-03-10']]),
        ]);

        $march = $this->entriesOf($result['months'], 3);
        self::assertSame('overdue', $march[0]['status']);
    }

    public function testDueSoonObligationGetsTheWarningDot(): void
    {
        // Annual due within 30 days of TODAY (2026-06-15) → due_soon, dot on July.
        $result = $this->build([
            $this->obligation('Próxima', Area::TRAINING, [[AlertFrequency::ANNUAL, '2026-07-01']]),
        ]);

        self::assertSame('due_soon', $this->entriesOf($result['months'], 7)[0]['status']);
    }

    public function testMonthlyOverdueDotsOnlyTheImminentMonth(): void
    {
        // Monthly, last due date just past → overdue. It shows in all twelve months, but the dot must
        // land only on the imminent month (June), never on the other eleven. This is the subtle case.
        $result = $this->build([
            $this->obligation('Mensual vencida', Area::CONSUMPTION, [[AlertFrequency::MONTHLY, '2026-06-10']]),
        ]);

        self::assertSame('overdue', $this->entriesOf($result['months'], 6)[0]['status']);
        foreach ($result['months'] as $cell) {
            if (6 !== $cell['month']) {
                self::assertNull($cell['entries'][0]['status'], "El mes {$cell['month']} no debe llevar punto de urgencia");
            }
        }
    }

    public function testEntryCarriesPillarFromChapterAndRouteFromArea(): void
    {
        // Pillar comes from the ISO chapter (OPERATION → Do), route from the linked area — two
        // independent dimensions, so an obligation is coloured even when colour and link differ.
        $withModule = $this->entriesOf($this->build([
            $this->obligation('Consumos', Area::CONSUMPTION, [[AlertFrequency::ANNUAL, '2026-05-01']], ObligationStatus::PENDING, IsoChapter::OPERATION),
        ])['months'], 5)[0];
        self::assertSame('do', $withModule['pillar']);
        self::assertSame('consumption_index', $withModule['route']);

        // No chapter and no module: null pillar and no module route (the template links it to its
        // register entry via the id instead, never a dead row).
        $bare = $this->entriesOf($this->build([
            $this->obligation('Sin módulo', null, [[AlertFrequency::ANNUAL, '2026-05-01']]),
        ])['months'], 5)[0];
        self::assertNull($bare['pillar']);
        self::assertNull($bare['route']);
    }
}
