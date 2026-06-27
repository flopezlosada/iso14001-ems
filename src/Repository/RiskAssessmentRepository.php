<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\RiskAssessment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RiskAssessment>
 */
class RiskAssessmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RiskAssessment::class);
    }

    /**
     * The valuations of a single exercise, with their risk/opportunity eagerly fetched to avoid an
     * N+1 (one query per row) while cloning.
     *
     * @param string $exercise the school year to load, in "YYYY-YYYY" format
     *
     * @return RiskAssessment[] the valuations of that exercise
     */
    public function findByExercise(string $exercise): array
    {
        return $this->createQueryBuilder('a')
            ->addSelect('ro')
            ->join('a.riskOpportunity', 'ro')
            ->where('a.exercise = :exercise')
            ->setParameter('exercise', $exercise)
            ->getQuery()
            ->getResult();
    }

    /**
     * The ids of the risks/opportunities that already have a valuation for an exercise. Used to skip
     * them when cloning, so the action never overwrites or duplicates an existing valuation.
     *
     * @param string $exercise the destination school year, in "YYYY-YYYY" format
     *
     * @return list<int> the risk/opportunity ids already valued for that exercise
     */
    public function findValuedRiskOpportunityIds(string $exercise): array
    {
        $rows = $this->createQueryBuilder('a')
            ->select('IDENTITY(a.riskOpportunity) AS roId')
            ->where('a.exercise = :exercise')
            ->setParameter('exercise', $exercise)
            ->getQuery()
            ->getScalarResult();

        return array_map(static fn (array $row): int => (int) $row['roId'], $rows);
    }
}
