<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\OperationalControlCheck;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OperationalControlCheck>
 */
class OperationalControlCheckRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OperationalControlCheck::class);
    }

    /**
     * The most recent inspections, newest month first, with their answers pre-loaded so the list can
     * show each one's non-conform count without an N+1.
     *
     * Done in two queries (page the headers, then load their answers) on purpose: a single
     * fetch-join with setMaxResults would limit join rows, not inspections, and cut the result.
     *
     * @param int $limit maximum number of inspections to return
     *
     * @return OperationalControlCheck[] the recent inspections, answers hydrated
     */
    public function findRecent(int $limit = 36): array
    {
        $checks = $this->findBy([], ['periodYear' => 'DESC', 'periodMonth' => 'DESC'], $limit);
        if ([] === $checks) {
            return [];
        }

        // Second query: load every answer of those inspections at once; Doctrine populates the
        // already-managed checks' collections, so countNonConform() no longer hits the database.
        $this->createQueryBuilder('c')
            ->addSelect('a')
            ->leftJoin('c.answers', 'a')
            ->where('c IN (:checks)')
            ->setParameter('checks', $checks)
            ->getQuery()
            ->getResult();

        return $checks;
    }

    /**
     * The inspection of a given month, or null if none has been recorded yet.
     *
     * @param int $year  the period year
     * @param int $month the period month (1-12)
     *
     * @return OperationalControlCheck|null the matching inspection, or null
     */
    public function findOneByPeriod(int $year, int $month): ?OperationalControlCheck
    {
        return $this->findOneBy(['periodYear' => $year, 'periodMonth' => $month]);
    }
}
