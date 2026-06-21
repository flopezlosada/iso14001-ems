<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AspectEvaluation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AspectEvaluation>
 */
class AspectEvaluationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AspectEvaluation::class);
    }
}
