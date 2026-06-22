<?php

declare(strict_types=1);

namespace App\Service\Import;

use App\Entity\CorrectiveAction;
use App\Entity\NonConformity;
use App\Enum\Efficacy;
use App\Enum\NonConformityOrigin;
use App\Enum\NonConformityStatus;
use App\Repository\NonConformityRepository;
use App\Service\NonConformityReferenceGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Imports the non-conformities register (F.11.0), one row per non-conformity with its single
 * corrective action. The centre's year-less code is mapped to the entity's parts (origin from the
 * prefix, sequence from the number, year from the opening date) and the reference is rebuilt with
 * {@see NonConformityReferenceGenerator} to keep one source of truth for its format.
 *
 * Idempotent by reference: re-importing updates the non-conformity in place and replaces its
 * corrective action (orphan removal), so a re-run converges to the same state.
 */
final class NonConformityImporter extends AbstractDatasetImporter implements DatasetImporter
{
    public function __construct(
        private readonly NonConformityRepository $nonConformities,
        private readonly NonConformityReferenceGenerator $referenceGenerator,
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
    ) {
    }

    public function key(): string
    {
        return 'non-conformities';
    }

    public function csvFilename(): string
    {
        return 'non_conformities.csv';
    }

    public function import(iterable $rows, bool $dryRun): ImportReport
    {
        $report = new ImportReport();
        $line = 1; // header is line 1

        foreach ($rows as $row) {
            ++$line;

            $origin = NonConformityOrigin::tryFrom($row['origin'] ?? '');
            if (null === $origin) {
                $report->reject($line, sprintf('Origen desconocido: "%s".', $row['origin'] ?? ''), $row);
                continue;
            }

            $status = NonConformityStatus::tryFrom($row['status'] ?? '');
            if (null === $status) {
                $report->reject($line, sprintf('Estado desconocido: "%s".', $row['status'] ?? ''), $row);
                continue;
            }

            $openedAt = $this->parseDate($row['opened_at'] ?? '');
            if (null === $openedAt) {
                $report->reject($line, sprintf('Fecha de apertura inválida o ausente: "%s".', $row['opened_at'] ?? ''), $row);
                continue;
            }

            $year = (int) ($row['year'] ?? 0);
            $sequence = (int) ($row['sequence'] ?? 0);
            $reference = $this->referenceGenerator->format($origin->code(), $year, $sequence);

            $nc = $this->nonConformities->findOneBy(['reference' => $reference]);
            $isNew = null === $nc;
            $nc ??= new NonConformity();

            $nc->setReference($reference)
                ->setOrigin($origin)
                ->setOriginDetail($this->nullable($row['origin_detail'] ?? ''))
                ->setYear($year)
                ->setSequence($sequence)
                ->setIsoClause($this->nullable($row['iso_clause'] ?? ''))
                ->setDescription(trim($row['description'] ?? ''))
                ->setRootCause($this->nullable($row['root_cause'] ?? ''))
                ->setStatus($status)
                ->setOpenedAt($openedAt)
                ->setClosedAt($this->parseDate($row['closed_at'] ?? ''));

            $this->syncCorrectiveAction($nc, $row);

            $violations = $this->validator->validate($nc);
            if (\count($violations) > 0) {
                $report->reject($line, $this->formatViolations($violations), $row);
                continue;
            }

            if ($isNew) {
                $this->entityManager->persist($nc);
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
     * Brings the non-conformity's single corrective action in line with the row, keeping the import
     * idempotent. The existing action is updated in place rather than deleted and recreated: a
     * delete + re-insert with the same (non-conformity, sequence) would violate the unique key
     * within one flush (Doctrine inserts before deleting). Extra actions, if any, are removed; when
     * the row carries no action, all are removed.
     *
     * @param array<string, string> $row
     */
    private function syncCorrectiveAction(NonConformity $nc, array $row): void
    {
        $actions = $nc->getCorrectiveActions()->toArray();
        $description = trim($row['action_description'] ?? '');

        if ('' === $description) {
            foreach ($actions as $existing) {
                $nc->removeCorrectiveAction($existing);
            }

            return;
        }

        // Reuse the first existing action; drop any extras (the register has a single action).
        $action = array_shift($actions);
        foreach ($actions as $extra) {
            $nc->removeCorrectiveAction($extra);
        }

        if (!$action instanceof CorrectiveAction) {
            $action = (new CorrectiveAction())->setSequence(1);
            $nc->addCorrectiveAction($action);
        }

        $action->setDescription($description)
            ->setEfficacy(Efficacy::tryFrom($row['action_efficacy'] ?? ''));
    }

    /**
     * Parses an ISO date (YYYY-MM-DD) to an immutable date at midnight, or null for an empty or
     * malformed value.
     */
    private function parseDate(string $value): ?\DateTimeImmutable
    {
        $value = trim($value);
        if ('' === $value) {
            return null;
        }

        return \DateTimeImmutable::createFromFormat('!Y-m-d', $value) ?: null;
    }
}
