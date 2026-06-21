<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CorrectiveAction;
use App\Entity\NonConformity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CorrectiveAction>
 */
class CorrectiveActionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CorrectiveAction::class);
    }

    /**
     * Next sequential number for a corrective action within the given non-conformity, i.e. the
     * current maximum plus one (1 when none exists yet).
     *
     * @param NonConformity $nonConformity the parent non-conformity
     *
     * @return int the next sequence number (>= 1)
     */
    public function nextSequence(NonConformity $nonConformity): int
    {
        $max = $this->createQueryBuilder('ca')
            ->select('MAX(ca.sequence)')
            ->where('ca.nonConformity = :nc')
            ->setParameter('nc', $nonConformity)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $max + 1;
    }
}
