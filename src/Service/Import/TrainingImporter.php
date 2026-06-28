<?php

declare(strict_types=1);

namespace App\Service\Import;

use App\Entity\TrainingAction;
use App\Enum\TrainingType;
use App\Repository\TrainingActionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Imports the annual training plan (form F.03.0) from the normalized training CSV.
 *
 * Natural key is (plan year, description, target audience): the F.03.0 sheet carries no code or id,
 * and the same description recurs within a year for different audiences, so the audience is part of
 * the identity — re-importing updates the matching action instead of duplicating it (or worse,
 * collapsing distinct actions into one). The execution dates and delivery type arrive as free text
 * and are normalized by {@see TrainingDateNormalizer} / {@see TrainingType}; when a value cannot be
 * normalized the row is NOT quarantined — it is imported with that field left null, the action flagged
 * for review and the original raw text recorded in its review note, so the centre fixes it in the UI
 * (see {@see TrainingAction}). Only a degenerate natural key (blank year/description/audience) is
 * still rejected, since such a row cannot be identified at all.
 *
 * Re-running the importer re-applies the CSV verbatim (it is the cutover loader, not a continuous
 * sync), so it re-sets the review flag from the source — manual fixes made in the UI are meant to
 * happen after the cutover, not to survive another full ETL pass.
 */
final class TrainingImporter extends AbstractDatasetImporter implements DatasetImporter
{
    public function __construct(
        private readonly TrainingActionRepository $actions,
        private readonly TrainingDateNormalizer $dateNormalizer,
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
    ) {
    }

    public function key(): string
    {
        return 'training';
    }

    public function csvFilename(): string
    {
        return 'training.csv';
    }

    public function import(iterable $rows, bool $dryRun): ImportReport
    {
        $report = new ImportReport();
        $line = 1; // header is line 1

        // Caches the actions seen so far in THIS run, keyed by natural key, so a duplicate natural
        // key within the same CSV updates the pending entity instead of persisting a second one
        // (it would not yet be found by the repository, which only sees flushed rows).
        /** @var array<string, TrainingAction> $seen */
        $seen = [];

        foreach ($rows as $row) {
            ++$line;

            $year = (int) ($row['plan_year'] ?? 0);
            if ($year < 2000 || $year > 2100) {
                $report->reject($line, sprintf('Año de plan inválido: "%s".', $row['plan_year'] ?? ''), $row);
                continue;
            }

            // Description and target audience are both part of the natural key, so an empty value
            // would degenerate the lookup: these are the only fields that still reject the row.
            $description = trim($row['description'] ?? '');
            if ('' === $description) {
                $report->reject($line, 'La descripción del curso no puede estar vacía.', $row);
                continue;
            }
            $targetAudience = trim($row['target_audience'] ?? '');
            if ('' === $targetAudience) {
                $report->reject($line, 'El destinatario (target_audience) no puede estar vacío.', $row);
                continue;
            }

            // Reasons the centre must review this row by hand: each un-normalizable value leaves its
            // field null and records why, instead of dropping the whole row to quarantine.
            $reviewNotes = [];

            $rawType = trim($row['type'] ?? '');
            $type = $this->mapType($rawType);
            if (null === $type) {
                $reviewNotes[] = '' === $rawType
                    ? 'Tipo EXT/INT sin especificar.'
                    : sprintf('Tipo EXT/INT no reconocido: "%s".', $rawType);
            }

            $rawPlanned = trim($row['planned_date'] ?? '');
            $plannedDate = $this->dateNormalizer->normalize($rawPlanned);
            if (null === $plannedDate) {
                $reviewNotes[] = '' === $rawPlanned
                    ? 'Fecha prevista sin especificar.'
                    : sprintf('Fecha prevista no normalizable: "%s".', $rawPlanned);
            }

            // The actual date is optional: an empty cell legitimately means "not delivered yet"
            // (null, no review needed); a non-empty cell that cannot be normalized is flagged.
            $rawActual = trim($row['actual_date'] ?? '');
            $actualDate = $this->dateNormalizer->normalize($rawActual);
            if ('' !== $rawActual && null === $actualDate) {
                $reviewNotes[] = sprintf('Fecha real no normalizable: "%s".', $rawActual);
            }

            $naturalKey = $year.'|'.$description.'|'.$targetAudience;
            $action = $seen[$naturalKey] ?? $this->actions->findOneByNaturalKey($year, $description, $targetAudience);
            $isNew = null === $action;
            $action ??= new TrainingAction();

            $action->setPlanYear($year)
                ->setDescription($description)
                ->setType($type)
                ->setTargetAudience($targetAudience)
                ->setObjectives(trim($row['objectives'] ?? ''))
                ->setPlannedDate($plannedDate)
                ->setMethodology(trim($row['methodology'] ?? ''))
                ->setActualDate($actualDate)
                ->setEfficacyEvaluation($this->nullable($row['efficacy_evaluation'] ?? ''))
                ->setNeedsReview([] !== $reviewNotes)
                ->setReviewNote([] === $reviewNotes ? null : implode(' ', $reviewNotes));

            $violations = $this->validator->validate($action);
            if (\count($violations) > 0) {
                $report->reject($line, $this->formatViolations($violations), $row);
                continue;
            }

            if ([] !== $reviewNotes) {
                $report->flag($line, implode(' ', $reviewNotes));
            }

            if ($isNew) {
                $this->entityManager->persist($action);
                $report->created();
            } else {
                $report->updated();
            }

            // Only cache valid, persisted entities, so a later duplicate row reuses this one.
            $seen[$naturalKey] = $action;
        }

        if (!$dryRun) {
            $this->entityManager->flush();
        }

        return $report;
    }

    /**
     * Maps the raw "EXT/INT" cell to a {@see TrainingType}, or null when it is blank, ambiguous or
     * unrecognized (e.g. "int/ext"), so the action is flagged for review instead of defaulting to one
     * delivery mode.
     */
    private function mapType(string $raw): ?TrainingType
    {
        return TrainingType::tryFrom(strtolower(trim($raw)));
    }
}
