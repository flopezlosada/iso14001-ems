<?php

declare(strict_types=1);

namespace App\Service\Import;

/**
 * Reads a normalized CSV (UTF-8, comma-separated, first row is the header) lazily as a sequence of
 * associative rows keyed by column name. Kept dependency-free on purpose: the import command only
 * ever consumes the clean CSV the ETL emits, so a thin {@see \fgetcsv} wrapper is enough.
 */
final class CsvReader
{
    /**
     * Yields one associative array per data row, keyed by the header columns.
     *
     * @param string $path absolute path to the CSV file
     *
     * @return iterable<array<string, string>> the data rows (header excluded)
     *
     * @throws \RuntimeException when the file cannot be opened or has no header
     */
    public function read(string $path): iterable
    {
        $handle = @fopen($path, 'r');
        if (false === $handle) {
            throw new \RuntimeException(sprintf('No se pudo abrir el CSV "%s".', $path));
        }

        try {
            $header = fgetcsv($handle);
            if (false === $header) {
                throw new \RuntimeException(sprintf('El CSV "%s" está vacío (sin cabecera).', $path));
            }
            /** @var list<string> $columns */
            $columns = array_map(static fn ($c) => (string) $c, $header);

            while (false !== ($values = fgetcsv($handle))) {
                // Skip fully blank lines, which fgetcsv returns as [null].
                if ([null] === $values) {
                    continue;
                }
                // Pad short rows and truncate long ones so array_combine always matches the header.
                $values = array_slice(array_pad($values, count($columns), ''), 0, count($columns));
                yield array_combine($columns, array_map(static fn ($v) => (string) ($v ?? ''), $values));
            }
        } finally {
            fclose($handle);
        }
    }
}
