<?php

declare(strict_types=1);

namespace App\Service\Import;

use App\Entity\Supplier;
use App\Entity\SupplierEvaluation;
use App\Enum\SupplierCriterion;
use App\Repository\SupplierRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Imports the supplier control register ("F.12.0") from the normalized CSV (one row per supplier
 * and year). The supplier is upserted by name; its yearly evaluation is upserted in place by year
 * (the entity has a unique (supplier, year), so a delete + re-insert would clash within one flush).
 *
 * Each row is validated on a transient candidate before touching the managed entity, so an invalid
 * row never mutates (and thus never flushes) a supplier already in the database.
 */
final class SupplierImporter extends AbstractDatasetImporter implements DatasetImporter
{
    public function __construct(
        private readonly SupplierRepository $suppliers,
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
    ) {
    }

    public function key(): string
    {
        return 'suppliers';
    }

    public function csvFilename(): string
    {
        return 'suppliers.csv';
    }

    public function import(iterable $rows, bool $dryRun): ImportReport
    {
        $report = new ImportReport();
        $line = 1; // header is line 1
        // In-call identity map: several rows share a supplier and it is not flushed until the end,
        // so findOneBy would not see the one created by an earlier row.
        $seen = [];

        foreach ($rows as $row) {
            ++$line;

            $criterion = SupplierCriterion::tryFrom($row['criterion'] ?? '');
            if (null === $criterion) {
                $report->reject($line, sprintf('Criterio de proveedor desconocido: "%s".', $row['criterion'] ?? ''), $row);
                continue;
            }

            $name = trim($row['supplier_name'] ?? '');
            $service = trim($row['product_or_service'] ?? '');
            $year = (int) ($row['year'] ?? 0);

            // Validate on a throwaway candidate so an invalid row never mutates a managed supplier.
            $candidate = (new Supplier())->setName($name)->setProductOrService($service);
            $candidate->addEvaluation((new SupplierEvaluation())->setYear($year)->setCriterion($criterion));
            $violations = $this->validator->validate($candidate);
            if (\count($violations) > 0) {
                $report->reject($line, $this->formatViolations($violations), $row);
                continue;
            }

            $supplier = $seen[$name] ?? $this->suppliers->findOneBy(['name' => $name]);
            if (null === $supplier) {
                $supplier = (new Supplier())->setName($name)->setProductOrService($service);
                $this->entityManager->persist($supplier);
            } else {
                $supplier->setProductOrService($service);
            }
            $seen[$name] = $supplier;

            if ($this->upsertEvaluation($supplier, $year, $criterion)) {
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
     * Updates the supplier's evaluation for the given year in place, or adds a new one. Returns true
     * when a new evaluation was created, false when an existing one was updated.
     */
    private function upsertEvaluation(Supplier $supplier, int $year, SupplierCriterion $criterion): bool
    {
        foreach ($supplier->getEvaluations() as $evaluation) {
            if ($evaluation->getYear() === $year) {
                $evaluation->setCriterion($criterion);

                return false;
            }
        }

        $supplier->addEvaluation((new SupplierEvaluation())->setYear($year)->setCriterion($criterion));

        return true;
    }
}
