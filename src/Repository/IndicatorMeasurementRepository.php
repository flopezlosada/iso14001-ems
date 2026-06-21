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
}
