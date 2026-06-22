<?php

declare(strict_types=1);

namespace App\Service\Import;

/**
 * Accumulates the outcome of importing one dataset: how many rows were created, updated or
 * rejected. Rejected rows are never dropped silently — they are kept here with their reason and
 * raw data so the command can write them to a quarantine file for manual review.
 */
final class ImportReport
{
    private int $created = 0;
    private int $updated = 0;

    /**
     * @var list<array{line: int, reason: string, data: array<string, string>}>
     */
    private array $rejected = [];

    /**
     * Records that a new entity was created from a row.
     */
    public function created(): void
    {
        ++$this->created;
    }

    /**
     * Records that an existing entity was updated from a row (idempotent re-import).
     */
    public function updated(): void
    {
        ++$this->updated;
    }

    /**
     * Records a row that could not be imported, keeping it for quarantine.
     *
     * @param int                   $line   1-based line number in the source CSV (header is line 1)
     * @param string                $reason human-readable explanation of the rejection
     * @param array<string, string> $data   the raw CSV row, preserved verbatim
     */
    public function reject(int $line, string $reason, array $data): void
    {
        $this->rejected[] = ['line' => $line, 'reason' => $reason, 'data' => $data];
    }

    public function getCreated(): int
    {
        return $this->created;
    }

    public function getUpdated(): int
    {
        return $this->updated;
    }

    /**
     * @return list<array{line: int, reason: string, data: array<string, string>}>
     */
    public function getRejected(): array
    {
        return $this->rejected;
    }

    /**
     * Total rows successfully persisted (created plus updated).
     */
    public function getProcessed(): int
    {
        return $this->created + $this->updated;
    }
}
