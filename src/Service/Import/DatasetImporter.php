<?php

declare(strict_types=1);

namespace App\Service\Import;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Imports one normalized dataset (the clean CSV produced by the offline ETL) into the database.
 *
 * Implementations must be idempotent: running an import twice over the same CSV leaves the
 * database in the same state (upsert by natural key), so the cutover can be re-run safely.
 */
#[AutoconfigureTag('app.dataset_importer')]
interface DatasetImporter
{
    /**
     * Stable key used to select this importer from the command (e.g. "consumptions").
     */
    public function key(): string;

    /**
     * Name of the CSV file this importer reads inside the data directory (e.g. "consumptions.csv").
     */
    public function csvFilename(): string;

    /**
     * Imports the given rows, upserting by natural key and validating each entity before persisting.
     * Invalid rows are recorded in the report (never persisted), not thrown.
     *
     * @param iterable<array<string, string>> $rows   parsed CSV rows keyed by column name
     * @param bool                             $dryRun when true, nothing is flushed to the database
     *
     * @return ImportReport the outcome (created / updated / rejected)
     */
    public function import(iterable $rows, bool $dryRun): ImportReport;
}
