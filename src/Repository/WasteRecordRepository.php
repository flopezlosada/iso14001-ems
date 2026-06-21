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
}
