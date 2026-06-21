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
}
