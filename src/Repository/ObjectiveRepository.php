<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Objective;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Objective>
 */
class ObjectiveRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Objective::class);
    }

    /**
     * All objectives ordered by their sequence, ready to render the list.
     *
     * @return Objective[] all objectives
     */
    public function findAllOrdered(): array
    {
        return $this->findBy([], ['sequence' => 'ASC']);
    }

    /**
     * Next sequential number for a new objective (current maximum plus one; 1 when none exist).
     *
     * @return int the next sequence number (>= 1)
     */
    public function nextSequence(): int
    {
        $max = $this->createQueryBuilder('o')
            ->select('MAX(o.sequence)')
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $max + 1;
    }
}
