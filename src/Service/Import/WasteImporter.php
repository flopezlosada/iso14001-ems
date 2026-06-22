<?php

declare(strict_types=1);

namespace App\Service\Import;

use App\Entity\WasteRecord;
use App\Repository\WasteRecordRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Imports the chronological waste register from the normalized waste CSV.
 *
 * The register has no reliable natural key, so idempotency is keyed on a deterministic hash of the
 * source row ({@see WasteRecord::$sourceHash}): re-importing the same CSV updates in place instead
 * of duplicating, while records created manually (null hash) are left untouched.
 */
final class WasteImporter extends AbstractDatasetImporter implements DatasetImporter
{
    public function __construct(
        private readonly WasteRecordRepository $records,
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
    ) {
    }

    public function key(): string
    {
        return 'waste';
    }

    public function csvFilename(): string
    {
        return 'waste.csv';
    }

    public function import(iterable $rows, bool $dryRun): ImportReport
    {
        $report = new ImportReport();
        $line = 1; // header is line 1

        foreach ($rows as $row) {
            ++$line;

            $pickup = trim($row['pickup_date'] ?? '');
            $date = null;
            if ('' !== $pickup) {
                $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $pickup) ?: null;
                if (null === $date) {
                    $report->reject($line, sprintf('Fecha de retirada con formato inesperado: "%s".', $pickup), $row);
                    continue;
                }
            }

            $hash = $this->hash($row);
            $record = $this->records->findOneBy(['sourceHash' => $hash]);
            $isNew = null === $record;
            $record ??= new WasteRecord();

            $record->setLerCode($this->nullable($row['ler_code'] ?? ''))
                ->setDescription(trim($row['description'] ?? ''))
                ->setQuantityKg($this->nullable($row['quantity_kg'] ?? ''))
                ->setPickupDate($date)
                ->setManager($this->nullable($row['manager'] ?? ''))
                ->setHazardous('1' === trim($row['hazardous'] ?? '0'))
                ->setNotes($this->nullable($row['notes'] ?? ''))
                ->setSourceHash($hash);

            $violations = $this->validator->validate($record);
            if (\count($violations) > 0) {
                $report->reject($line, $this->formatViolations($violations), $row);
                continue;
            }

            if ($isNew) {
                $this->entityManager->persist($record);
                $report->created();
            } else {
                $report->updated();
            }
        }

        if (!$dryRun) {
            $this->entityManager->flush();
        }

        return $report;
    }

    /**
     * Deterministic content hash of a source row, used as the idempotency key. Any change to a
     * field yields a different hash, so an edited register row is re-imported as a NEW record and
     * the previous one is NOT removed automatically. After correcting the ETL output and re-running,
     * clean the orphans manually (rows whose source_hash is absent from the new CSV).
     *
     * @param array<string, string> $row
     */
    private function hash(array $row): string
    {
        return hash('sha256', implode("\x1f", [
            $row['ler_code'] ?? '',
            $row['description'] ?? '',
            $row['quantity_kg'] ?? '',
            $row['pickup_date'] ?? '',
            $row['manager'] ?? '',
            $row['hazardous'] ?? '',
            $row['notes'] ?? '',
        ]));
    }
}
