<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ConsumptionReading;
use App\Enum\ConsumptionType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ConsumptionReading>
 */
class ConsumptionReadingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ConsumptionReading::class);
    }

    /**
     * Returns every reading of a given year, ordered by month and then type, ready to render the
     * yearly consumption table.
     *
     * @param int $year the period year
     *
     * @return ConsumptionReading[] readings of that year
     */
    public function findForYear(int $year): array
    {
        return $this->findBy(
            ['periodYear' => $year],
            ['periodMonth' => 'ASC', 'type' => 'ASC'],
        );
    }

    /**
     * Finds the single reading for a utility in a given month, or null if not recorded yet.
     *
     * @param ConsumptionType $type  the utility
     * @param int             $year  the period year
     * @param int             $month the period month (1-12)
     *
     * @return ConsumptionReading|null the matching reading, or null
     */
    public function findOneByPeriod(ConsumptionType $type, int $year, int $month): ?ConsumptionReading
    {
        return $this->findOneBy([
            'type' => $type,
            'periodYear' => $year,
            'periodMonth' => $month,
        ]);
    }

    /**
     * Sums the recorded quantity of a utility for a year, limited to the months up to and including
     * a cut-off month (the year-to-date window). Comparing the same calendar window across years is
     * what makes the interannual intensity estimate fair and seasonality-proof.
     *
     * Returns null only when there is no reading at all in that window; a window that has readings
     * which genuinely sum to zero returns the string '0', not null. So null means "nothing to
     * compare", while '0' is real data (a 100% drop against a non-zero baseline).
     *
     * @param ConsumptionType $type      the utility
     * @param int             $year      the period year
     * @param int             $upToMonth inclusive cut-off month (1-12); 12 sums the whole year
     *
     * @return numeric-string|null the summed quantity as a decimal string, or null when no reading exists
     */
    public function sumQuantityForYearToDate(ConsumptionType $type, int $year, int $upToMonth): ?string
    {
        /** @var numeric-string|null $sum */
        $sum = $this->createQueryBuilder('r')
            ->select('SUM(r.quantity)')
            ->andWhere('r.type = :type')
            ->andWhere('r.periodYear = :year')
            ->andWhere('r.periodMonth <= :upToMonth')
            ->setParameter('type', $type)
            ->setParameter('year', $year)
            ->setParameter('upToMonth', $upToMonth)
            ->getQuery()
            ->getSingleScalarResult();

        return $sum;
    }

    /**
     * The latest month with a reading for a utility in a year, or null when the year has none. Used
     * as the comparison cut-off so the current (partial) year is compared against the same number
     * of months of the baseline year, avoiding a systematic bias from the unfinished year.
     *
     * @param ConsumptionType $type the utility
     * @param int             $year the period year
     *
     * @return int|null the latest recorded month (1-12), or null when there is no reading
     */
    public function lastRecordedMonth(ConsumptionType $type, int $year): ?int
    {
        $max = $this->createQueryBuilder('r')
            ->select('MAX(r.periodMonth)')
            ->andWhere('r.type = :type')
            ->andWhere('r.periodYear = :year')
            ->setParameter('type', $type)
            ->setParameter('year', $year)
            ->getQuery()
            ->getSingleScalarResult();

        return null === $max ? null : (int) $max;
    }

    /**
     * Yearly totals of a utility's quantity (year => summed quantity), ascending by year and only
     * for years that have readings. Feeds the multi-year consumption trend.
     *
     * @param ConsumptionType $type the utility
     *
     * @return array<int, numeric-string> summed quantity per year
     */
    public function yearlyTotals(ConsumptionType $type): array
    {
        /** @var list<array{year: int, total: numeric-string}> $rows */
        $rows = $this->createQueryBuilder('r')
            ->select('r.periodYear AS year, SUM(r.quantity) AS total')
            ->andWhere('r.type = :type')
            ->setParameter('type', $type)
            ->groupBy('r.periodYear')
            ->orderBy('r.periodYear', 'ASC')
            ->getQuery()
            ->getResult();

        $totals = [];
        foreach ($rows as $row) {
            $totals[$row['year']] = $row['total'];
        }

        return $totals;
    }
}
