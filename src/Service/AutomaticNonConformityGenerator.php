<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\NonConformity;
use App\Enum\NonConformityOrigin;
use App\Repository\IndicatorMeasurementRepository;
use App\Repository\NonConformityRepository;
use App\Repository\ObjectiveRepository;
use App\Util\SchoolYear;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Opens non-conformities automatically (PC.10.0) from the conditions the centre enabled in the
 * settings: indicator measurements that breach their reference value (F.09.0) and objectives marked
 * as not achieved (PG-06.04). Each rule is an opt-in toggle ({@see \App\Entity\Settings}); nothing
 * happens while they are off.
 *
 * It is a reconciliation pass meant to run on a schedule (idempotent): every candidate carries a
 * stable source key ({@see NonConformity::getAutoSourceKey()}), so a source that already has a
 * non-conformity is skipped and the same one is never opened twice — safe to run as often as wanted.
 */
final class AutomaticNonConformityGenerator
{
    /** ISO 14001:2015 clause recorded on the non-conformity for each rule. */
    private const string CLAUSE_INDICATOR = '9.1';
    private const string CLAUSE_OBJECTIVE = '6.2';

    public function __construct(
        private readonly SettingsProvider $settings,
        private readonly IndicatorMeasurementRepository $measurements,
        private readonly ObjectiveRepository $objectives,
        private readonly NonConformityRepository $nonConformities,
        private readonly NonConformityReferenceGenerator $referenceGenerator,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Runs every enabled rule and opens the missing non-conformities.
     *
     * @param \DateTimeImmutable $on reference date (today): the opening date of the created records
     *
     * @return array{candidates: int, created: int} how many sources qualified and how many
     *                                               non-conformities were opened
     */
    public function generate(\DateTimeImmutable $on): array
    {
        $settings = $this->settings->get();

        $candidates = [];
        if ($settings->isAutoNcFromBreachedIndicators()) {
            $candidates = [...$candidates, ...$this->indicatorCandidates()];
        }
        if ($settings->isAutoNcFromUnmetObjectives()) {
            $candidates = [...$candidates, ...$this->objectiveCandidates(SchoolYear::current($on))];
        }

        return [
            'candidates' => \count($candidates),
            'created' => $this->openMissing($candidates, $on),
        ];
    }

    /**
     * Opens a non-conformity for every candidate that does not have one yet, in a single batch.
     *
     * @param list<array{key: string, originDetail: string, description: string, isoClause: string}> $candidates
     * @param \DateTimeImmutable                                                                       $on
     *
     * @return int the number of non-conformities opened
     */
    private function openMissing(array $candidates, \DateTimeImmutable $on): int
    {
        if ([] === $candidates) {
            return 0;
        }

        // Idempotency: drop the candidates that already have a non-conformity (one query for all).
        $existing = array_fill_keys(
            $this->nonConformities->findExistingAutoSourceKeys(array_column($candidates, 'key')),
            true,
        );

        // All auto non-conformities share the INTERNAL origin; reserve the sequence once and
        // increment it in memory, because nextSequence() reads the DB and would hand the same number
        // to every record before the flush (breaking the unique origin/year/sequence constraint).
        $year = (int) $on->format('Y');
        $sequence = $this->nonConformities->nextSequence(NonConformityOrigin::INTERNAL, $year);

        $created = 0;
        foreach ($candidates as $candidate) {
            if (isset($existing[$candidate['key']])) {
                continue;
            }
            $existing[$candidate['key']] = true; // guard against duplicate candidates within the run

            $nc = (new NonConformity())
                ->setOrigin(NonConformityOrigin::INTERNAL)
                ->setOpenedAt($on)
                ->setYear($year)
                ->setSequence($sequence)
                ->setReference($this->referenceGenerator->format(NonConformityOrigin::INTERNAL->code(), $year, $sequence))
                ->setOriginDetail($candidate['originDetail'])
                ->setDescription($candidate['description'])
                ->setIsoClause($candidate['isoClause'])
                ->setAutoSourceKey($candidate['key']);

            $this->em->persist($nc);
            ++$sequence;
            ++$created;
        }

        if ($created > 0) {
            $this->em->flush();
        }

        return $created;
    }

    /**
     * Candidate non-conformities for the breached indicator measurements.
     *
     * @return list<array{key: string, originDetail: string, description: string, isoClause: string}>
     */
    private function indicatorCandidates(): array
    {
        $out = [];
        foreach ($this->measurements->findBreached() as $measurement) {
            $indicator = $measurement->getIndicator();
            $reference = $indicator->getReferenceValue();
            $out[] = [
                'key' => 'indicator_measurement:'.$measurement->getId(),
                'originDetail' => sprintf('Indicador «%s» (%02d/%d)', $indicator->getName(), $measurement->getMonth(), $measurement->getYear()),
                'description' => sprintf(
                    'El indicador «%s» quedó fuera de su valor de referencia en %02d/%d (valor registrado: %s%s).',
                    $indicator->getName(),
                    $measurement->getMonth(),
                    $measurement->getYear(),
                    $measurement->getValue(),
                    null !== $reference ? '; referencia: '.$reference : '',
                ),
                'isoClause' => self::CLAUSE_INDICATOR,
            ];
        }

        return $out;
    }

    /**
     * Candidate non-conformities for the objectives of a course marked as not achieved. Scoped to the
     * current course so closed courses' objectives are not reopened indefinitely (PG-06.04).
     *
     * @param string $schoolYear the current school year, in "YYYY-YYYY" format
     *
     * @return list<array{key: string, originDetail: string, description: string, isoClause: string}>
     */
    private function objectiveCandidates(string $schoolYear): array
    {
        $out = [];
        foreach ($this->objectives->findNotAchievedForSchoolYear($schoolYear) as $objective) {
            $out[] = [
                'key' => 'objective:'.$objective->getId(),
                'originDetail' => sprintf('Objetivo %s', $objective->getReference()),
                'description' => sprintf(
                    'El objetivo ambiental «%s» (%s) se marcó como no cumplido.',
                    $objective->getReference(),
                    $objective->getDescription(),
                ),
                'isoClause' => self::CLAUSE_OBJECTIVE,
            ];
        }

        return $out;
    }
}
