<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\NonConformity;
use App\Entity\SystemAudit;
use App\Enum\NonConformityOrigin;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NonConformity>
 */
class NonConformityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NonConformity::class);
    }

    /**
     * Every non-conformity, most recently opened first, ready to render the control list (F.11.0).
     *
     * @return NonConformity[] all non-conformities
     */
    public function findAllOrdered(): array
    {
        return $this->findBy([], ['openedAt' => 'DESC', 'id' => 'DESC']);
    }

    /**
     * Next sequential number for the given origin and year, i.e. the current maximum plus one
     * (1 when none exists yet). The sequence resets each year.
     *
     * @param NonConformityOrigin $origin the non-conformity origin
     * @param int                 $year   the reference year
     *
     * @return int the next sequence number (>= 1)
     */
    public function nextSequence(NonConformityOrigin $origin, int $year): int
    {
        $max = $this->createQueryBuilder('nc')
            ->select('MAX(nc.sequence)')
            ->where('nc.origin = :origin')
            ->andWhere('nc.year = :year')
            ->setParameter('origin', $origin)
            ->setParameter('year', $year)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $max + 1;
    }

    /**
     * The non-conformities raised in a given audit, most recently opened first. Used to list an
     * audit's findings on its detail page.
     *
     * @param SystemAudit $audit the audit
     *
     * @return NonConformity[] the audit's non-conformities
     */
    public function findByAudit(SystemAudit $audit): array
    {
        return $this->findBy(['audit' => $audit], ['openedAt' => 'DESC', 'id' => 'DESC']);
    }

    /**
     * Counts, per audit origin, the audit non-conformities of a given reference year, in a single
     * query (so there is no per-audit lookup). Used by the management-review summary to report how
     * many findings the year's audits produced.
     *
     * @param int $year the reference year
     *
     * @return array<string, int> count keyed by {@see NonConformityOrigin} value, only for
     *                            the audit origins that have at least one
     */
    public function countAuditFindingsByOriginForYear(int $year): array
    {
        $rows = $this->createQueryBuilder('nc')
            ->select('nc.origin AS origin', 'COUNT(nc.id) AS total')
            ->where('nc.origin IN (:origins)')
            ->andWhere('nc.year = :year')
            ->setParameter('origins', [NonConformityOrigin::INTERNAL_AUDIT, NonConformityOrigin::EXTERNAL_AUDIT])
            ->setParameter('year', $year)
            ->groupBy('nc.origin')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[$row['origin']->value] = (int) $row['total'];
        }

        return $counts;
    }
}
