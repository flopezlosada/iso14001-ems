<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\NonConformity;
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
}
