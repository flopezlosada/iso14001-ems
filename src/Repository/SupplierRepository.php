<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Supplier;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Supplier>
 */
class SupplierRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Supplier::class);
    }

    /**
     * All suppliers with their yearly evaluations eagerly fetched, so the control list can show each
     * supplier's standing for the current year without an N+1 over each one's evaluations.
     *
     * @return Supplier[] all suppliers ordered by name, evaluations preloaded (most recent first)
     */
    public function findAllWithEvaluations(): array
    {
        return $this->createQueryBuilder('s')
            ->addSelect('e')
            ->leftJoin('s.evaluations', 'e')
            ->orderBy('s.name', 'ASC')
            ->addOrderBy('e.year', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
