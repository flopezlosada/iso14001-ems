<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\WasteRecord;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WasteRecord>
 */
class WasteRecordRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WasteRecord::class);
    }

    /**
     * Returns the waste records most recently picked up, newest first.
     *
     * @param int $limit maximum number of records
     *
     * @return WasteRecord[] the records
     */
    public function findRecent(int $limit = 200): array
    {
        return $this->findBy([], ['pickupDate' => 'DESC', 'id' => 'DESC'], $limit);
    }

    /**
     * Sums the kilograms of the given LER codes picked up in a year, up to and including a cut-off
     * month (year-to-date), so the same calendar window can be compared across years. Uses a date
     * range instead of YEAR()/MONTH() so no DQL date-function extension is needed.
     *
     * Returns null only when there is no matching record at all (so the caller can tell "no data"
     * from a genuine zero).
     *
     * @param list<string> $lerCodes  the LER codes that make up the aspect
     * @param int          $year      the period year
     * @param int          $upToMonth inclusive cut-off month (1-12)
     *
     * @return numeric-string|null the summed kilograms, or null when no record matches
     */
    public function sumKgForYearToDate(array $lerCodes, int $year, int $upToMonth): ?string
    {
        if ([] === $lerCodes) {
            return null;
        }

        $start = new \DateTimeImmutable(sprintf('%d-01-01', $year));
        $end = $start->modify(sprintf('+%d months', $upToMonth)); // first day after the cut-off month

        /** @var numeric-string|null $sum */
        $sum = $this->createQueryBuilder('w')
            ->select('SUM(w.quantityKg)')
            ->andWhere('w.lerCode IN (:codes)')
            ->andWhere('w.pickupDate >= :start')
            ->andWhere('w.pickupDate < :end')
            ->setParameter('codes', $lerCodes)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult();

        return $sum;
    }

    /**
     * The month (1-12) of the latest pickup of the given LER codes in a year, or null when the year
     * has none. Used as the comparison cut-off so a partial current year is matched like-for-like.
     *
     * @param list<string> $lerCodes the LER codes that make up the aspect
     * @param int          $year     the period year
     *
     * @return int|null the latest pickup month, or null
     */
    public function lastRecordedMonth(array $lerCodes, int $year): ?int
    {
        if ([] === $lerCodes) {
            return null;
        }

        $start = new \DateTimeImmutable(sprintf('%d-01-01', $year));
        $end = $start->modify('+1 year');

        $last = $this->createQueryBuilder('w')
            ->select('MAX(w.pickupDate)')
            ->andWhere('w.lerCode IN (:codes)')
            ->andWhere('w.pickupDate >= :start')
            ->andWhere('w.pickupDate < :end')
            ->setParameter('codes', $lerCodes)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult();

        // MAX(date) over a DATE column comes back as a 'Y-m-d' scalar; take its month.
        return null === $last ? null : (int) (new \DateTimeImmutable((string) $last))->format('n');
    }

    /**
     * Yearly totals of waste removed (year => summed kilograms), ascending by year and only for years
     * that have at least one weighed pick-up. Optionally filtered by the hazardous flag, so the trend
     * can be split into peligrosos / no peligrosos.
     *
     * Records with no pick-up date (year unknown) or no weight in kilograms are skipped: they cannot
     * be placed on a yearly axis. The real register holds plenty of both (free-text dates, amounts in
     * non-weight units), so this null-tolerance is load-bearing, not theoretical.
     *
     * Aggregation is done in PHP over the (small) register to avoid needing a DQL YEAR() date-function
     * extension, consistent with {@see sumKgForYearToDate()} and {@see lastRecordedMonth()}.
     *
     * @param bool|null $hazardous when not null, restrict to records with that hazardous flag
     *
     * @return array<int, float> summed kilograms per year, ascending by year
     */
    public function yearlyTotalsKg(?bool $hazardous = null): array
    {
        $qb = $this->createQueryBuilder('w')
            ->select('w.pickupDate AS pickupDate, w.quantityKg AS quantityKg')
            ->andWhere('w.pickupDate IS NOT NULL')
            ->andWhere('w.quantityKg IS NOT NULL');

        if (null !== $hazardous) {
            $qb->andWhere('w.hazardous = :hazardous')->setParameter('hazardous', $hazardous);
        }

        // Selecting the field directly (not an aggregate like MAX) makes the ORM apply the column's
        // date_immutable type, so pickupDate comes back hydrated as a DateTimeImmutable, not a string.
        /** @var list<array{pickupDate: \DateTimeImmutable, quantityKg: numeric-string}> $rows */
        $rows = $qb->getQuery()->getResult();

        $totals = [];
        foreach ($rows as $row) {
            $year = (int) $row['pickupDate']->format('Y');
            $totals[$year] = ($totals[$year] ?? 0.0) + (float) $row['quantityKg'];
        }

        ksort($totals);

        return $totals;
    }
}
