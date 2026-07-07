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

    /**
     * The given year's evaluations that belong to an active aspect, to derive the yearly workflow
     * status in a single query (no N+1 walking each aspect's evaluations). The unique (aspect, year)
     * constraint means at most one row per active aspect.
     *
     * @param int $year the evaluation year
     *
     * @return AspectEvaluation[] this year's evaluations of active aspects
     */
    public function findByYearForActiveAspects(int $year): array
    {
        return $this->createQueryBuilder('e')
            ->join('e.aspect', 'a')
            ->andWhere('e.year = :year')
            ->andWhere('a.active = true')
            ->setParameter('year', $year)
            ->getQuery()
            ->getResult();
    }
}
