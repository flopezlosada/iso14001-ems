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
}
