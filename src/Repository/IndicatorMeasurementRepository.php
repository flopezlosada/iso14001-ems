<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\IndicatorMeasurement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<IndicatorMeasurement>
 */
class IndicatorMeasurementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, IndicatorMeasurement::class);
    }

    /**
     * Every measurement marked as breaching its indicator's reference value, with the indicator
     * eagerly joined so the auto-non-conformity engine reads its name without an extra query per row
     * (no N+1). Ordered oldest first so older breaches get the lower reference numbers.
     *
     * @return IndicatorMeasurement[] the breached measurements
     */
    public function findBreached(): array
    {
        return $this->createQueryBuilder('m')
            ->addSelect('i')
            ->join('m.indicator', 'i')
            ->where('m.breached = true')
            ->orderBy('m.year', 'ASC')
            ->addOrderBy('m.month', 'ASC')
            ->addOrderBy('m.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
