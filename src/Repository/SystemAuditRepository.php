<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SystemAudit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SystemAudit>
 */
class SystemAuditRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SystemAudit::class);
    }

    /**
     * Every audit, most recent year first (and within a year by conduction date), ready to render
     * the list.
     *
     * @return SystemAudit[] all audits ordered for display
     */
    public function findAllOrdered(): array
    {
        return $this->findBy([], ['year' => 'DESC', 'conductedOn' => 'DESC', 'id' => 'DESC']);
    }

    /**
     * The audits filed for a given year, ordered by type and conduction date. Used by the
     * management-review summary provider.
     *
     * @param int $year the audit year
     *
     * @return SystemAudit[] the year's audits
     */
    public function findForYear(int $year): array
    {
        return $this->findBy(
            ['year' => $year],
            ['type' => 'ASC', 'conductedOn' => 'ASC', 'id' => 'ASC'],
        );
    }
}
