<?php

declare(strict_types=1);

namespace App\Service\Import;

use App\Entity\LegalRequirement;
use App\Enum\EvaluationFrequency;
use App\Enum\LegalScope;
use App\Repository\LegalRequirementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Imports the legal requirements register ("Requisitos Legales") from the normalized CSV.
 *
 * Natural key is the reference (RL-NN). Compliance status is not touched: the source does not carry
 * it reliably, so new records keep the entity default (pending) and existing ones keep whatever a
 * user has set.
 */
final class LegalRequirementImporter extends AbstractDatasetImporter implements DatasetImporter
{
    public function __construct(
        private readonly LegalRequirementRepository $requirements,
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
    ) {
    }

    public function key(): string
    {
        return 'legal-requirements';
    }

    public function csvFilename(): string
    {
        return 'legal_requirements.csv';
    }

    public function import(iterable $rows, bool $dryRun): ImportReport
    {
        $report = new ImportReport();
        $line = 1; // header is line 1

        // In-call identity map by reference: a requirement created earlier in this run is not
        // flushed yet, so findOneBy would not see it and a repeated reference in the same CSV would
        // be persisted twice, tripping the unique reference on flush. The normalizer already folds
        // repeated references, but the importer must not crash if a hand-made CSV slips one through.
        $seen = [];

        foreach ($rows as $row) {
            ++$line;

            $scope = LegalScope::tryFrom($row['scope'] ?? '');
            if (null === $scope) {
                $report->reject($line, sprintf('Ámbito legal desconocido: "%s".', $row['scope'] ?? ''), $row);
                continue;
            }

            $reference = trim($row['reference'] ?? '');
            $requirement = $seen[$reference] ?? $this->requirements->findOneBy(['reference' => $reference]);
            $isNew = null === $requirement;
            $requirement ??= new LegalRequirement();

            $requirement->setReference($reference)
                ->setSequence((int) ($row['sequence'] ?? 0))
                ->setLegalProvision(trim($row['legal_provision'] ?? ''))
                ->setScope($scope)
                ->setEnvironmentalVector($this->nullable($row['environmental_vector'] ?? ''))
                ->setSpecificRequirement(trim($row['specific_requirement'] ?? ''))
                ->setComplianceEvidence($this->nullable($row['compliance_evidence'] ?? ''))
                ->setEvaluationFrequency(EvaluationFrequency::tryFrom($row['evaluation_frequency'] ?? ''))
                ->setLastReviewedOn($this->parseDate($row['last_reviewed_on'] ?? ''));

            // The next review is derived from the last review plus the cadence (see LegalRequirement).
            // Only override it when the CSV carries an explicit date (a regulator-fixed inspection that
            // does not follow the cadence); an empty column must NOT wipe the derived value.
            $explicitNextReview = $this->parseDate($row['next_review_on'] ?? '');
            if (null !== $explicitNextReview) {
                $requirement->setNextReviewOn($explicitNextReview);
            }

            $violations = $this->validator->validate($requirement);
            if (\count($violations) > 0) {
                $report->reject($line, $this->formatViolations($violations), $row);
                continue;
            }

            if ($isNew) {
                $this->entityManager->persist($requirement);
                $report->created();
            } else {
                $report->updated();
            }
            $seen[$reference] = $requirement;
        }

        if (!$dryRun) {
            $this->entityManager->flush();
        }

        return $report;
    }

    /**
     * Parses an ISO date (YYYY-MM-DD) to an immutable date, or null for an empty/malformed value.
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
