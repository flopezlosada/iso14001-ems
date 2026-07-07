<?php

declare(strict_types=1);

namespace App\Tests\Service\Overview;

use App\Entity\Document;
use App\Entity\ScheduledAlert;
use App\Enum\AlertFrequency;
use App\Enum\Area;
use App\Enum\DocumentType;
use App\Enum\IsoChapter;
use App\Enum\ModuleHealth;
use App\Enum\ObligationStatus;
use App\Enum\PdcaPhase;
use App\Security\Voter\AreaVoter;
use App\Service\Overview\ModuleOverview;
use App\Service\Overview\ModuleWorkflowContributor;
use App\Service\Overview\ModuleWorkflowSignal;
use App\Service\Overview\SystemOverviewBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Unit tests for {@see SystemOverviewBuilder}: the global map's per-module health. These pin the two
 * signal sources and how they merge — the date-based obligation semaphore (works for every module)
 * and the workflow contributor (for the few modules with a real flow, so an on-schedule-but-
 * incomplete module is not a false green) — plus the READ permission filtering and the PDCA grouping.
 */
final class SystemOverviewBuilderTest extends TestCase
{
    /** A fixed "today" so urgency and school year are deterministic. */
    private const TODAY = '2026-03-15';

    /**
     * Builds the aggregator with an auth checker granting READ for the given areas (null = all, an
     * admin bypass) and the given contributors. Obligations are passed to build() by each test.
     *
     * @param list<Area>|null                 $readable     areas the user may read (null = all)
     * @param list<ModuleWorkflowContributor> $contributors the workflow contributors
     */
    private function builder(?array $readable, array $contributors = []): SystemOverviewBuilder
    {
        $checker = $this->createMock(AuthorizationCheckerInterface::class);
        $checker->method('isGranted')->willReturnCallback(
            static fn (string $attribute, mixed $subject): bool => AreaVoter::READ === $attribute
                && (null === $readable || \in_array($subject, $readable, true)),
        );

        return new SystemOverviewBuilder($checker, $contributors);
    }

    /**
     * An obligation linked to the given area, with an optional review alert of the given cadence due
     * on the given date (null date = no scheduled date, e.g. for an on-event alert).
     */
    private function obligation(Area $area, ObligationStatus $status, ?\DateTimeImmutable $due, AlertFrequency $frequency = AlertFrequency::ANNUAL): Document
    {
        $document = (new Document())
            ->setTitle('Obligación de '.$area->value)
            ->setType(DocumentType::RECORD)
            ->setIsoChapter(IsoChapter::PLANNING)
            ->setStatus($status)
            ->setLinkedArea($area);

        if (null !== $due || AlertFrequency::ON_EVENT === $frequency) {
            $alert = (new ScheduledAlert())->setFrequency($frequency);
            if (null !== $due) {
                $alert->setNextDueDate($due);
            }
            $document->addAlert($alert);
        }

        return $document;
    }

    private function today(): \DateTimeImmutable
    {
        return new \DateTimeImmutable(self::TODAY);
    }

    /**
     * Finds the single module card for the given area in the built groups, failing if it is absent.
     *
     * @param list<array{phase: PdcaPhase, modules: list<ModuleOverview>}> $groups
     */
    private function cardFor(array $groups, Area $area): ModuleOverview
    {
        foreach ($groups as $group) {
            foreach ($group['modules'] as $module) {
                if ($module->area === $area) {
                    return $module;
                }
            }
        }

        self::fail(sprintf('No module card for %s', $area->value));
    }

    public function testOverdueObligationMakesModuleGrave(): void
    {
        $groups = $this->builder([Area::CONSUMPTION])->build(
            [$this->obligation(Area::CONSUMPTION, ObligationStatus::PENDING, $this->today()->modify('-10 days'))],
            $this->today(),
        );

        $card = $this->cardFor($groups, Area::CONSUMPTION);
        self::assertSame(ModuleHealth::GRAVE, $card->health);
        self::assertSame('1 vencida', $card->summary);
    }

    public function testDueSoonObligationMakesModuleAttention(): void
    {
        $groups = $this->builder([Area::CONSUMPTION])->build(
            [$this->obligation(Area::CONSUMPTION, ObligationStatus::PENDING, $this->today()->modify('+10 days'))],
            $this->today(),
        );

        $card = $this->cardFor($groups, Area::CONSUMPTION);
        self::assertSame(ModuleHealth::ATTENTION, $card->health);
        self::assertSame('1 próxima a vencer', $card->summary);
    }

    public function testDoneObligationReadsAlDiaEvenIfDatePassed(): void
    {
        // Marked done though its date passed: done work is applicable but not pending, so OK, not grave.
        $groups = $this->builder([Area::CONSUMPTION])->build(
            [$this->obligation(Area::CONSUMPTION, ObligationStatus::DONE, $this->today()->modify('-40 days'))],
            $this->today(),
        );

        $card = $this->cardFor($groups, Area::CONSUMPTION);
        self::assertSame(ModuleHealth::OK, $card->health);
        self::assertSame('al día', $card->summary);
    }

    public function testEventDrivenObligationReadsAlDia(): void
    {
        // A purely on-event obligation (no fixed cadence) is never "due": still PENDING, it must read
        // as "al día" on purpose, not by omission — the module is not falsely flagged.
        $groups = $this->builder([Area::WASTE])->build(
            [$this->obligation(Area::WASTE, ObligationStatus::PENDING, null, AlertFrequency::ON_EVENT)],
            $this->today(),
        );

        $card = $this->cardFor($groups, Area::WASTE);
        self::assertSame(ModuleHealth::OK, $card->health);
        self::assertSame('al día', $card->summary);
    }

    public function testModuleWithOnlyNotApplicableObligationsIsNeutral(): void
    {
        $groups = $this->builder([Area::SUPPLIER])->build(
            [$this->obligation(Area::SUPPLIER, ObligationStatus::NOT_APPLICABLE, $this->today()->modify('-5 days'))],
            $this->today(),
        );

        $card = $this->cardFor($groups, Area::SUPPLIER);
        self::assertSame(ModuleHealth::NEUTRAL, $card->health);
        self::assertSame('sin obligaciones activas', $card->summary);
    }

    public function testNotApplicableIsIgnoredAlongsideAnApplicableObligation(): void
    {
        // One NOT_APPLICABLE overdue (must be ignored, NOT grave) plus one applicable due-soon: the
        // applicable one drives the card, proving the skip is per-obligation, not global.
        $groups = $this->builder([Area::SUPPLIER])->build(
            [
                $this->obligation(Area::SUPPLIER, ObligationStatus::NOT_APPLICABLE, $this->today()->modify('-30 days')),
                $this->obligation(Area::SUPPLIER, ObligationStatus::PENDING, $this->today()->modify('+5 days')),
            ],
            $this->today(),
        );

        $card = $this->cardFor($groups, Area::SUPPLIER);
        self::assertSame(ModuleHealth::ATTENTION, $card->health);
        self::assertSame('1 próxima a vencer', $card->summary);
    }

    public function testWorstUrgencyWinsAcrossManyObligations(): void
    {
        // Two obligations of the same module: one due soon, one overdue. Overdue must win.
        $groups = $this->builder([Area::WASTE])->build(
            [
                $this->obligation(Area::WASTE, ObligationStatus::PENDING, $this->today()->modify('+10 days')),
                $this->obligation(Area::WASTE, ObligationStatus::PENDING, $this->today()->modify('-2 days')),
            ],
            $this->today(),
        );

        $card = $this->cardFor($groups, Area::WASTE);
        self::assertSame(ModuleHealth::GRAVE, $card->health);
        self::assertSame('1 vencida', $card->summary);
    }

    public function testObligationWithoutLinkedAreaIsIgnored(): void
    {
        // Framework documents (manual, procedures) have no linked area: they must not leak into any
        // module card. An overdue one with linkedArea = null leaves Consumos as "sin obligaciones".
        $orphan = (new Document())
            ->setTitle('Manual de Gestión')
            ->setType(DocumentType::RECORD)
            ->setIsoChapter(IsoChapter::PLANNING)
            ->setStatus(ObligationStatus::PENDING)
            ->addAlert((new ScheduledAlert())->setFrequency(AlertFrequency::ANNUAL)->setNextDueDate($this->today()->modify('-20 days')));

        $groups = $this->builder([Area::CONSUMPTION])->build([$orphan], $this->today());

        $card = $this->cardFor($groups, Area::CONSUMPTION);
        self::assertSame(ModuleHealth::NEUTRAL, $card->health);
        self::assertSame('sin obligaciones activas', $card->summary);
    }

    public function testWorkflowContributorElevatesOnScheduleModule(): void
    {
        // The obligation is on track (beyond the soon window), but the workflow says work is pending:
        // the module must NOT show as a false green — the contributor's specific summary takes over.
        $contributor = $this->contributor(Area::RISK_OPPORTUNITY, new ModuleWorkflowSignal(ModuleHealth::ATTENTION, '2 sin valorar, 1 sin aprobar'));

        $groups = $this->builder([Area::RISK_OPPORTUNITY], [$contributor])->build(
            [$this->obligation(Area::RISK_OPPORTUNITY, ObligationStatus::PENDING, $this->today()->modify('+90 days'))],
            $this->today(),
        );

        $card = $this->cardFor($groups, Area::RISK_OPPORTUNITY);
        self::assertSame(ModuleHealth::ATTENTION, $card->health);
        self::assertSame('2 sin valorar, 1 sin aprobar', $card->summary);
    }

    public function testOverdueObligationOutranksWorkflowSignal(): void
    {
        // Obligation overdue (grave) AND workflow pending (attention): the worse colour wins and keeps
        // its own overdue wording, because an overdue review is more urgent than a pending flow step.
        $contributor = $this->contributor(Area::RISK_OPPORTUNITY, new ModuleWorkflowSignal(ModuleHealth::ATTENTION, '2 sin valorar'));

        $groups = $this->builder([Area::RISK_OPPORTUNITY], [$contributor])->build(
            [$this->obligation(Area::RISK_OPPORTUNITY, ObligationStatus::PENDING, $this->today()->modify('-1 day'))],
            $this->today(),
        );

        $card = $this->cardFor($groups, Area::RISK_OPPORTUNITY);
        self::assertSame(ModuleHealth::GRAVE, $card->health);
        self::assertSame('1 vencida', $card->summary);
    }

    public function testNullContributorSignalLeavesObligationSignalUntouched(): void
    {
        // A complete workflow returns null: the obligation semaphore (OK) stands.
        $contributor = $this->contributor(Area::RISK_OPPORTUNITY, null);

        $groups = $this->builder([Area::RISK_OPPORTUNITY], [$contributor])->build(
            [$this->obligation(Area::RISK_OPPORTUNITY, ObligationStatus::DONE, $this->today()->modify('-5 days'))],
            $this->today(),
        );

        $card = $this->cardFor($groups, Area::RISK_OPPORTUNITY);
        self::assertSame(ModuleHealth::OK, $card->health);
        self::assertSame('al día', $card->summary);
    }

    public function testTwoContributorsForTheSameAreaFailLoudly(): void
    {
        $one = $this->contributor(Area::RISK_OPPORTUNITY, null);
        $two = $this->contributor(Area::RISK_OPPORTUNITY, null);

        $this->expectException(\LogicException::class);
        $this->builder([Area::RISK_OPPORTUNITY], [$one, $two])->build([], $this->today());
    }

    public function testOnlyReadableModulesAppearAndEmptyPhasesAreDropped(): void
    {
        // Secretaría-like: only Consumos + Residuos, both in the Do phase.
        $groups = $this->builder([Area::CONSUMPTION, Area::WASTE])->build([], $this->today());

        self::assertCount(1, $groups, 'Only the Do phase should survive.');
        $areas = array_map(static fn (ModuleOverview $m): Area => $m->area, $groups[0]['modules']);
        self::assertSame([Area::CONSUMPTION, Area::WASTE], $areas);
    }

    public function testAdminSeesEveryModule(): void
    {
        // null readable = admin bypass: every area gets a card, none missing.
        $groups = $this->builder(null)->build([], $this->today());

        $count = array_sum(array_map(static fn (array $g): int => \count($g['modules']), $groups));
        self::assertSame(\count(Area::cases()), $count);
    }

    /**
     * A stub workflow contributor for the given area, returning the given signal (or null).
     */
    private function contributor(Area $area, ?ModuleWorkflowSignal $signal): ModuleWorkflowContributor
    {
        return new class($area, $signal) implements ModuleWorkflowContributor {
            public function __construct(private readonly Area $area, private readonly ?ModuleWorkflowSignal $signal)
            {
            }

            public function area(): Area
            {
                return $this->area;
            }

            public function summarize(string $exercise): ?ModuleWorkflowSignal
            {
                return $this->signal;
            }
        };
    }
}
