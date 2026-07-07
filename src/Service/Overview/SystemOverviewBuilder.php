<?php

declare(strict_types=1);

namespace App\Service\Overview;

use App\Entity\Document;
use App\Enum\Area;
use App\Enum\ModuleHealth;
use App\Enum\ObligationStatus;
use App\Enum\ObligationUrgency;
use App\Enum\PdcaPhase;
use App\Security\Voter\AreaVoter;
use App\Util\SchoolYear;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Builds the "estado del sistema" map of the dashboard: every functional {@see Area} the current
 * user may read, grouped by PDCA phase (the same shape and order as the sidebar menu), each with a
 * one-glance {@see ModuleHealth} and the concrete pending step for the current school year.
 *
 * Each module's health has two sources, merged so the worst wins and the most specific text shows:
 *  - a date-based semaphore over the module's own obligations (their review urgency), which works
 *    for every module with zero extra code;
 *  - for the few modules with a real workflow, a {@see ModuleWorkflowContributor} that adds the
 *    concrete missing step (e.g. Riesgos "2 sin valorar"), so an on-schedule-but-incomplete module
 *    is never shown as a false green.
 *
 * The obligations are passed in already loaded (their alerts eager-loaded), shared with the personal
 * worklist, so the dashboard classifies urgency across the whole register without any N+1 and
 * without querying it twice.
 */
final readonly class SystemOverviewBuilder
{
    /**
     * @param iterable<ModuleWorkflowContributor> $contributors the per-area workflow contributors (auto-tagged)
     */
    public function __construct(
        private AuthorizationCheckerInterface $authChecker,
        #[AutowireIterator('app.module_workflow_contributor')]
        private iterable $contributors,
    ) {
    }

    /**
     * The overview of the modules the user may read, grouped by PDCA phase; phases with no readable
     * module are omitted (as in the menu).
     *
     * The obligations are passed in (already loaded once by the caller) rather than queried here, so
     * the dashboard does not fetch the register twice — the personal worklist reads the same list.
     *
     * @param Document[]         $obligations the register's obligations, already loaded by the caller
     * @param \DateTimeImmutable $on          the reference date (today), which fixes both the urgency and the school year
     *
     * @return list<array{phase: PdcaPhase, modules: list<ModuleOverview>}> phases in cycle order, each with its module cards
     */
    public function build(array $obligations, \DateTimeImmutable $on): array
    {
        $exercise = SchoolYear::current($on);
        $obligationsByArea = $this->groupByArea($obligations);
        $contributorsByArea = $this->contributorsByArea();

        $groups = [];
        foreach (Area::groupedByPhase() as $group) {
            $modules = [];
            foreach ($group['areas'] as $area) {
                if (!$this->authChecker->isGranted(AreaVoter::READ, $area)) {
                    continue; // the user cannot see this module; leave it out entirely
                }
                $modules[] = $this->overviewFor(
                    $area,
                    $obligationsByArea[$area->value] ?? [],
                    $contributorsByArea[$area->value] ?? null,
                    $exercise,
                    $on,
                );
            }
            if ([] !== $modules) {
                $groups[] = ['phase' => $group['phase'], 'modules' => $modules];
            }
        }

        return $groups;
    }

    /**
     * Builds one module card, merging the obligation semaphore with the module's workflow signal
     * (worst health wins; the more specific summary is kept when it is at least as urgent).
     *
     * @param Area                           $area        the module this card describes
     * @param Document[]                     $obligations the module's linked obligations
     * @param ModuleWorkflowContributor|null $contributor the module's workflow contributor, if any
     * @param string                         $exercise    the current school year
     * @param \DateTimeImmutable             $on          the reference date
     */
    private function overviewFor(Area $area, array $obligations, ?ModuleWorkflowContributor $contributor, string $exercise, \DateTimeImmutable $on): ModuleOverview
    {
        [$health, $summary] = $this->obligationSignal($obligations, $on);

        $signal = $contributor?->summarize($exercise);
        if (null !== $signal) {
            // A signal at least as urgent as the obligation one is more specific, so it labels the card;
            // an obligation that is worse (e.g. overdue) still wins the colour and keeps its own text.
            if ($signal->health->severity() >= $health->severity()) {
                $summary = $signal->summary;
            }
            if ($signal->health->isWorseThan($health)) {
                $health = $signal->health;
            }
        }

        return new ModuleOverview($area, $health, $summary);
    }

    /**
     * The date-based semaphore of a module from its obligations: overdue beats due-soon beats
     * on-track. Not-applicable obligations are ignored; done ones count as applicable (the work
     * exists) but not as pending, so a fully-done module reads as "al día", not overdue.
     *
     * @param Document[] $obligations the module's linked obligations
     * @param \DateTimeImmutable     $on          the reference date
     *
     * @return array{0: ModuleHealth, 1: string} the health and its summary text
     */
    private function obligationSignal(array $obligations, \DateTimeImmutable $on): array
    {
        $overdue = 0;
        $dueSoon = 0;
        $hasApplicable = false;

        foreach ($obligations as $obligation) {
            $status = $obligation->getStatus();
            if (ObligationStatus::NOT_APPLICABLE === $status) {
                continue;
            }
            $hasApplicable = true;
            if (ObligationStatus::DONE === $status) {
                continue; // done for the period: applicable but not pending
            }

            match ($obligation->dueStatus($on)) {
                ObligationUrgency::OVERDUE => ++$overdue,
                ObligationUrgency::DUE_SOON => ++$dueSoon,
                default => null,
            };
        }

        if ($overdue > 0) {
            return [ModuleHealth::GRAVE, sprintf('%d vencida%s', $overdue, 1 === $overdue ? '' : 's')];
        }
        if ($dueSoon > 0) {
            return [ModuleHealth::ATTENTION, sprintf('%d próxima%s a vencer', $dueSoon, 1 === $dueSoon ? '' : 's')];
        }
        if ($hasApplicable) {
            return [ModuleHealth::OK, 'al día'];
        }

        return [ModuleHealth::NEUTRAL, 'sin obligaciones activas'];
    }

    /**
     * Groups the given obligations by the module (linked area) they are filled in; obligations with
     * no linked area (framework documents, manual) are skipped.
     *
     * @param Document[] $obligations the register's obligations
     *
     * @return array<string, list<Document>> map of Area value → its obligations
     */
    private function groupByArea(array $obligations): array
    {
        $byArea = [];
        foreach ($obligations as $obligation) {
            $area = $obligation->getLinkedArea();
            if (null !== $area) {
                $byArea[$area->value][] = $obligation;
            }
        }

        return $byArea;
    }

    /**
     * The workflow contributors indexed by their area value, so each module finds its own (if any) in
     * constant time. Two contributors for the same area is a wiring mistake (the interface promises
     * one per area), so it fails loudly rather than letting one silently shadow the other.
     *
     * @return array<string, ModuleWorkflowContributor> map of Area value → its contributor
     *
     * @throws \LogicException when two contributors declare the same area
     */
    private function contributorsByArea(): array
    {
        $byArea = [];
        foreach ($this->contributors as $contributor) {
            $areaValue = $contributor->area()->value;
            if (isset($byArea[$areaValue])) {
                throw new \LogicException(sprintf('Two workflow contributors declare the area "%s"; each area may have at most one.', $areaValue));
            }
            $byArea[$areaValue] = $contributor;
        }

        return $byArea;
    }
}
