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
     * The audit non-conformities of a reference year (internal or external audit origin), with their
     * corrective actions and each action's responsible eagerly loaded in a single query, ordered by
     * reference. Lets the management-review summary list every finding with its resolution plan
     * without a per-non-conformity or per-action lookup (no N+1).
     *
     * @param int $year the reference year
     *
     * @return NonConformity[] the year's audit findings
     */
    public function findAuditFindingsForYear(int $year): array
    {
        return $this->createQueryBuilder('nc')
            ->leftJoin('nc.correctiveActions', 'ca')->addSelect('ca')
            ->leftJoin('ca.responsible', 'r')->addSelect('r')
            ->where('nc.origin IN (:origins)')
            ->andWhere('nc.year = :year')
            ->setParameter('origins', [NonConformityOrigin::INTERNAL_AUDIT, NonConformityOrigin::EXTERNAL_AUDIT])
            ->setParameter('year', $year)
            ->orderBy('nc.reference', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Of the given auto-source keys, the ones that already have a non-conformity, in a single query
     * (so the auto-generation engine checks idempotency without a lookup per candidate — no N+1).
     *
     * @param string[] $keys the candidate source keys (e.g. "indicator_measurement:123")
     *
     * @return string[] the subset of keys that already exist
     */
    public function findExistingAutoSourceKeys(array $keys): array
    {
        if ([] === $keys) {
            return [];
        }

        $rows = $this->createQueryBuilder('nc')
            ->select('nc.autoSourceKey')
            ->where('nc.autoSourceKey IN (:keys)')
            ->setParameter('keys', $keys)
            ->getQuery()
            ->getSingleColumnResult();

        return array_map(strval(...), $rows);
    }
}
