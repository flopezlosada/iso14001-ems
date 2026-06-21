<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\EmergencyDrill;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EmergencyDrill>
 */
class EmergencyDrillRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EmergencyDrill::class);
    }

    /**
     * Returns the most recent emergency drills, newest first.
     *
     * @param int $limit maximum number of records
     *
     * @return EmergencyDrill[] the drills
     */
    public function findRecent(int $limit = 200): array
    {
        return $this->findBy([], ['drillDate' => 'DESC', 'id' => 'DESC'], $limit);
    }
}
