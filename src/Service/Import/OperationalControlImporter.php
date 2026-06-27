<?php

declare(strict_types=1);

namespace App\Service\Import;

use App\Entity\OperationalControlAnswer;
use App\Entity\OperationalControlCheck;
use App\Entity\OperationalControlItem;
use App\Enum\ConformityResult;
use App\Enum\OperationalControlSection;
use App\Repository\OperationalControlCheckRepository;
use App\Repository\OperationalControlItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Imports the monthly operational-control checklist ("RG-08.01.01") from the normalized CSV (one row
 * per checked item per month: its period, section, label, conformity result and optional remark).
 *
 * The checklist catalogue is seeded from the real sheet, not the synthetic fixture: each item is
 * upserted by its natural key (section, label) so re-running is idempotent and the genuine wording is
 * preserved. Each month is one inspection, upserted by (year, month) — the entity has a unique on
 * that period — and each item's outcome is upserted in place by (check, item) so a re-import never
 * duplicates a row within one flush.
 *
 * The form has a per-line remark ("SE HA QUEDADO ENCENDIDA") but the model only stores one note per
 * inspection ({@see OperationalControlCheck::$observations}); the per-item remarks are folded into it
 * as "label: remark" lines, rebuilt on every pass so the result stays idempotent. A dedicated
 * per-answer remark field would be more faithful but belongs to the PG-08.01 module, not the ETL.
 */
final class OperationalControlImporter extends AbstractDatasetImporter implements DatasetImporter
{
    public function __construct(
        private readonly OperationalControlItemRepository $items,
        private readonly OperationalControlCheckRepository $checks,
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
    ) {
    }

    public function key(): string
    {
        return 'operational_control';
    }

    public function csvFilename(): string
    {
        return 'operational_control.csv';
    }

    public function import(iterable $rows, bool $dryRun): ImportReport
    {
        $report = new ImportReport();
        // In-call identity maps: findOneBy cannot see entities not flushed yet, so an item or check
        // first seen on an earlier row must be reused from here, not re-created.
        $itemCache = [];
        $checkCache = [];
        // New items keep the catalogue's display order by first appearance, continuing past any
        // items already in the database.
        $nextPosition = $this->items->count([]);

        // Group rows by period first: the per-inspection note is rebuilt from all of a month's
        // remarks on every pass, so the month's rows must be handled together to stay idempotent.
        foreach ($this->groupByPeriod($rows, $report) as $group) {
            $check = $this->resolveCheck($group['year'], $group['month'], $group['performedBy'], $checkCache, $report, $group['firstLine'], $group['sample']);
            if (null === $check) {
                continue; // rejection already recorded for the whole period
            }

            $remarks = [];
            foreach ($group['rows'] as ['line' => $line, 'data' => $row]) {
                $section = OperationalControlSection::tryFrom(trim($row['section'] ?? ''));
                if (null === $section) {
                    $report->reject($line, sprintf('Sección desconocida: "%s".', $row['section'] ?? ''), $row);
                    continue;
                }
                $result = ConformityResult::tryFrom(trim($row['result'] ?? ''));
                if (null === $result) {
                    $report->reject($line, sprintf('Resultado desconocido: "%s".', $row['result'] ?? ''), $row);
                    continue;
                }
                $label = trim($row['item_label'] ?? '');

                $item = $this->resolveItem($section, $label, $itemCache, $nextPosition, $report, $line, $row);
                if (null === $item) {
                    continue; // rejection already recorded
                }

                $this->upsertAnswer($check, $item, $result) ? $report->created() : $report->updated();

                $remark = trim($row['observation'] ?? '');
                if ('' !== $remark) {
                    $remarks[] = $label.': '.$remark;
                }
            }
            // Rebuilt from scratch each pass (not appended) so a re-import is idempotent.
            $check->setObservations([] === $remarks ? null : implode("\n", $remarks));
        }

        if (!$dryRun) {
            $this->entityManager->flush();
        }

        return $report;
    }

    /**
     * Groups the CSV rows by inspection period, carrying the line number of each row for accurate
     * rejection reporting. Rows missing a parseable year/month are rejected here, never grouped.
     *
     * @param iterable<array<string, string>> $rows
     *
     * @return list<array{year: int, month: int, performedBy: string, firstLine: int, sample: array<string, string>, rows: list<array{line: int, data: array<string, string>}>}>
     */
    private function groupByPeriod(iterable $rows, ImportReport $report): array
    {
        $groups = [];
        $line = 1; // header is line 1
        foreach ($rows as $row) {
            ++$line;
            $year = (int) trim($row['year'] ?? '');
            $month = (int) trim($row['month'] ?? '');
            if ($year <= 0 || $month <= 0) {
                $report->reject($line, 'Año o mes ausente o no numérico.', $row);
                continue;
            }
            $key = $year.'-'.$month;
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'year' => $year,
                    'month' => $month,
                    'performedBy' => trim($row['performed_by'] ?? ''),
                    'firstLine' => $line,
                    'sample' => $row,
                    'rows' => [],
                ];
            }
            $groups[$key]['rows'][] = ['line' => $line, 'data' => $row];
        }

        return array_values($groups);
    }

    /**
     * Upserts the monthly inspection by (year, month), validating a throwaway candidate before
     * touching the managed graph. Returns null (recording a rejection) when the period is invalid.
     *
     * @param array<string, OperationalControlCheck> $cache  in-call identity map, keyed by "year-month"
     * @param array<string, string>                  $sample a representative row, kept with a rejection
     */
    private function resolveCheck(int $year, int $month, string $performedBy, array &$cache, ImportReport $report, int $line, array $sample): ?OperationalControlCheck
    {
        $key = $year.'-'.$month;
        $check = $cache[$key] ?? $this->checks->findOneBy(['periodYear' => $year, 'periodMonth' => $month]);
        if (null === $check) {
            $candidate = (new OperationalControlCheck())
                ->setPeriodYear($year)
                ->setPeriodMonth($month)
                ->setPerformedBy($performedBy);
            $violations = $this->validator->validate($candidate);
            if (\count($violations) > 0) {
                $report->reject($line, $this->formatViolations($violations), $sample);

                return null;
            }
            $check = $candidate;
            $this->entityManager->persist($check);
        } else {
            // The sheet is the source of record: refresh who performed it on re-import.
            $check->setPerformedBy('' !== $performedBy ? $performedBy : $check->getPerformedBy());
        }
        $cache[$key] = $check;

        return $check;
    }

    /**
     * Upserts a checklist item by its natural key (section, label), creating it on first sight with
     * the next display position. Returns null (recording a rejection) when the label is invalid.
     *
     * @param array<string, OperationalControlItem> $cache in-call identity map, keyed by "section|label"
     * @param array<string, string>                 $row   the source row, kept with a rejection
     */
    private function resolveItem(OperationalControlSection $section, string $label, array &$cache, int &$nextPosition, ImportReport $report, int $line, array $row): ?OperationalControlItem
    {
        $key = $section->value.'|'.$label;
        $item = $cache[$key] ?? $this->items->findOneBy(['section' => $section, 'label' => $label]);
        if (null === $item) {
            $candidate = (new OperationalControlItem())
                ->setSection($section)
                ->setLabel($label)
                ->setPosition($nextPosition)
                ->setActive(true);
            $violations = $this->validator->validate($candidate);
            if (\count($violations) > 0) {
                $report->reject($line, $this->formatViolations($violations), $row);

                return null;
            }
            $item = $candidate;
            ++$nextPosition;
            $this->entityManager->persist($item);
        }
        $cache[$key] = $item;

        return $item;
    }

    /**
     * Updates the item's outcome in this inspection in place, or adds a new answer, honouring the
     * unique (check, item) within one flush.
     *
     * @return bool true when a new answer was created, false when an existing one was updated
     */
    private function upsertAnswer(OperationalControlCheck $check, OperationalControlItem $item, ConformityResult $result): bool
    {
        foreach ($check->getAnswers() as $answer) {
            if ($answer->getItem() === $item) {
                $answer->setResult($result);

                return false;
            }
        }

        $answer = (new OperationalControlAnswer())->setItem($item)->setResult($result);
        $check->addAnswer($answer);

        return true;
    }
}
