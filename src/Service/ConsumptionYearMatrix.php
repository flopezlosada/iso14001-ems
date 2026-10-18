<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ConsumptionReading;
use App\Enum\ConsumptionType;

/**
 * Read model of one year of consumption laid out like the official form F-6.1.2: months as rows,
 * utilities as columns, plus the yearly total of each column. Pure lookup over readings already
 * loaded in a single query (no per-cell queries).
 */
final readonly class ConsumptionYearMatrix
{
    /**
     * @param array<int, array<string, ConsumptionReading>>                                $cells  month => type value => reading
     * @param array<string, array{quantity: numeric-string, cost: numeric-string|null}> $totals type value => yearly totals
     */
    private function __construct(
        private array $cells,
        private array $totals,
    ) {
    }

    /**
     * Indexes the readings of a year by month and type.
     *
     * @param iterable<ConsumptionReading>                                                  $readings readings of a single year
     * @param array<string, array{quantity: numeric-string, cost: numeric-string|null}> $totals   yearly totals per type value
     */
    public static function fromReadings(iterable $readings, array $totals): self
    {
        $cells = [];
        foreach ($readings as $reading) {
            $cells[$reading->getPeriodMonth()][$reading->getType()->value] = $reading;
        }

        return new self($cells, $totals);
    }

    /**
     * The reading of a utility in a month, or null when that cell has not been recorded yet.
     */
    public function reading(int $month, ConsumptionType $type): ?ConsumptionReading
    {
        return $this->cells[$month][$type->value] ?? null;
    }

    /**
     * The observations recorded in a month across all its readings, in column order and without
     * blanks or repetitions (the form has a single "observations" cell per month).
     *
     * @return list<string> the distinct notes of that month
     */
    public function notes(int $month): array
    {
        $notes = array_map(
            fn (ConsumptionType $type): string => trim($this->reading($month, $type)?->getNotes() ?? ''),
            ConsumptionType::cases(),
        );

        return array_values(array_unique(array_filter($notes, static fn (string $note): bool => '' !== $note)));
    }

    /**
     * Yearly totals of a utility, or null when it has no reading that year.
     *
     * @return array{quantity: numeric-string, cost: numeric-string|null}|null
     */
    public function total(ConsumptionType $type): ?array
    {
        return $this->totals[$type->value] ?? null;
    }
}
