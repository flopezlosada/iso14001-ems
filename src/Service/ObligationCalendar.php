<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Document;
use App\Enum\ObligationStatus;
use App\Enum\ObligationUrgency;

/**
 * Lays the periodic obligations onto a 12-month "year at a glance", starting at the configured month
 * so the view can span the centre's audit cycle rather than the calendar year.
 *
 * An obligation appears in every month its cadences fall due (a monthly reading shows in all twelve,
 * an annual review in one). The month is derived from each {@see \App\Entity\ScheduledAlert}'s next
 * due date and its frequency interval — month-of-year only, since this is a planning overview, not a
 * day planner. Event-driven obligations (no fixed cadence) carry no month and are returned apart.
 */
class ObligationCalendar
{
    /**
     * Builds the calendar structure for a set of obligations (already filtered by scope/permission
     * by the caller).
     *
     * @param iterable<Document> $obligations the obligations to lay out
     * @param int                $startMonth  the month (1-12) the 12-month window begins on
     * @param \DateTimeImmutable $today       reference date for the urgency marker
     *
     * @return array{months: list<array{month: int, entries: list<array<string, mixed>>}>, eventDriven: list<array<string, mixed>>}
     */
    public function build(iterable $obligations, int $startMonth, \DateTimeImmutable $today): array
    {
        /** @var array<int, list<array<string, mixed>>> $byMonth */
        $byMonth = array_fill(1, 12, []);
        $eventDriven = [];

        foreach ($obligations as $obligation) {
            if (ObligationStatus::NOT_APPLICABLE === $obligation->getStatus()) {
                continue;
            }

            $entry = $this->entry($obligation);
            $months = $this->dueMonths($obligation);
            if ([] === $months) {
                $eventDriven[] = $entry;

                continue;
            }

            // The urgency dot lands only on the imminent occurrence (the next review date's month),
            // so a monthly obligation does not paint twelve red dots.
            $nextReview = $obligation->nextReviewDate();
            $imminentMonth = null !== $nextReview ? (int) $nextReview->format('n') : null;
            $urgency = $obligation->dueStatus($today);
            $isUrgent = \in_array($urgency, [ObligationUrgency::OVERDUE, ObligationUrgency::DUE_SOON], true);

            foreach ($months as $month) {
                $byMonth[$month][] = [
                    ...$entry,
                    'status' => $isUrgent && $month === $imminentMonth ? $urgency->value : null,
                ];
            }
        }

        $months = [];
        for ($i = 0; $i < 12; ++$i) {
            $month = (($startMonth - 1 + $i) % 12) + 1;
            $months[] = ['month' => $month, 'entries' => $byMonth[$month]];
        }

        return ['months' => $months, 'eventDriven' => $eventDriven];
    }

    /**
     * The distinct months-of-year (1-12) on which the obligation falls due, unioned over its
     * fixed-cadence alerts. An annual alert hits one month, a biannual two (six apart), a monthly
     * one all twelve; event-driven alerts contribute none.
     *
     * The month is derived from each alert's next due date (not from the start of the year), then
     * the interval is projected across the twelve-month window. A not-yet-rolled past due date just
     * places the obligation in that (past) month too, which is fine for a planning overview.
     *
     * @return list<int>
     */
    private function dueMonths(Document $obligation): array
    {
        $months = [];
        foreach ($obligation->getAlerts() as $alert) {
            $interval = $alert->getFrequency()->intervalMonths();
            if (null === $interval) {
                continue;
            }
            $start = (int) $alert->getNextDueDate()->format('n');
            for ($k = 0; $k < 12 / $interval; ++$k) {
                $months[(($start - 1 + $k * $interval) % 12) + 1] = true;
            }
        }

        return array_keys($months);
    }

    /**
     * The display fields shared by every month cell the obligation appears in (status is added per
     * cell). Colour is the obligation's PDCA pillar (from its ISO chapter, like "Estructura SGA"), so
     * every obligation is coloured even without a module. The link points to its data module when it
     * has one, falling back in the template to the obligation's own register entry ({@see id}) — so
     * every item is reachable, never a dead row.
     *
     * @return array{id: ?int, title: string, code: ?string, route: ?string, pillar: ?string}
     */
    private function entry(Document $obligation): array
    {
        return [
            'id' => $obligation->getId(),
            'title' => $obligation->getTitle(),
            'code' => $obligation->getCode(),
            'route' => $obligation->getLinkedArea()?->indexRoute(),
            'pillar' => $obligation->getPhase()?->value,
        ];
    }
}
