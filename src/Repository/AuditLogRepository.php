<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AuditLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AuditLog>
 */
class AuditLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuditLog::class);
    }

    /**
     * Returns the most recent audit entries, newest first.
     *
     * @param int $limit maximum number of entries to return
     *
     * @return AuditLog[] the latest entries
     */
    public function findLatest(int $limit = 100): array
    {
        return $this->findBy([], ['occurredAt' => 'DESC', 'id' => 'DESC'], $limit);
    }
}
