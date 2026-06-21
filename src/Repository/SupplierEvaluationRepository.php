<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SupplierEvaluation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SupplierEvaluation>
 */
class SupplierEvaluationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SupplierEvaluation::class);
    }
}
