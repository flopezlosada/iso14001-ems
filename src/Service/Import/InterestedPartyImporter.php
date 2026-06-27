<?php

declare(strict_types=1);

namespace App\Service\Import;

use App\Entity\InterestedParty;
use App\Repository\InterestedPartyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Imports the interested parties register ("F.04.0 / PPI") from the normalized CSV (one row per
 * review year and party). Each party is upserted by its natural key (review year, name), so a
 * re-import updates the existing row in place instead of duplicating it.
 *
 * Each row is validated on a transient candidate before touching the managed entity, so an invalid
 * row never mutates (and thus never flushes) a party already in the database.
 */
final class InterestedPartyImporter extends AbstractDatasetImporter implements DatasetImporter
{
    public function __construct(
        private readonly InterestedPartyRepository $parties,
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
    ) {
    }

    public function key(): string
    {
        return 'interested-parties';
    }

    public function csvFilename(): string
    {
        return 'interested_parties.csv';
    }

    public function import(iterable $rows, bool $dryRun): ImportReport
    {
        $report = new ImportReport();
        $line = 1; // header is line 1
        // In-call identity map keyed by (year, name): a party created earlier in this run is not
        // flushed yet, so findOneBy would not see it.
        $seen = [];

        foreach ($rows as $row) {
            ++$line;

            $year = (int) ($row['review_year'] ?? 0);
            $name = trim($row['name'] ?? '');
            $needs = trim($row['needs_and_expectations'] ?? '');
            $incidents = $this->nullable($row['incidents'] ?? '');

            // Validate on a throwaway candidate so an invalid row never mutates a managed party.
            $candidate = (new InterestedParty())
                ->setReviewYear($year)
                ->setName($name)
                ->setNeedsAndExpectations($needs)
                ->setIncidents($incidents);
            $violations = $this->validator->validate($candidate);
            if (\count($violations) > 0) {
                $report->reject($line, $this->formatViolations($violations), $row);
                continue;
            }

            $key = $year.'|'.$name;
            $party = $seen[$key] ?? $this->parties->findOneBy(['reviewYear' => $year, 'name' => $name]);
            if (null === $party) {
                $party = (new InterestedParty())->setReviewYear($year)->setName($name);
                $this->entityManager->persist($party);
                $report->created();
            } else {
                $report->updated();
            }
            $party->setNeedsAndExpectations($needs)->setIncidents($incidents);
            $seen[$key] = $party;
        }

        if (!$dryRun) {
            $this->entityManager->flush();
        }

        return $report;
    }
}
