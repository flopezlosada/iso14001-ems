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
     * The valuations of a single exercise with their action plan eagerly fetched, so the workflow
     * status can count unapproved valuations and relevant valuations lacking an action plan without
     * an N+1 (one query per valuation for its actions).
     *
     * @param string $exercise the school year to load, in "YYYY-YYYY" format
     *
     * @return RiskAssessment[] the valuations of that exercise, with their actions
     */
    public function findByExerciseWithActions(string $exercise): array
    {
        return $this->createQueryBuilder('a')
            ->addSelect('ro', 'act')
            ->join('a.riskOpportunity', 'ro')
            ->leftJoin('a.actions', 'act')
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
