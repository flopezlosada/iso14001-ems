<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Objective;
use App\Enum\ObjectiveStatus;
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
     * The objectives of a single school year, ordered by sequence, ready to render that course's list.
     *
     * @param string $schoolYear the school year in "YYYY-YYYY" format
     *
     * @return Objective[] the objectives of that course
     */
    public function findForSchoolYear(string $schoolYear): array
    {
        return $this->findBy(['schoolYear' => $schoolYear], ['sequence' => 'ASC']);
    }

    /**
     * How many objectives are recorded for a school year. Used to decide whether the previous course
     * has anything to copy forward.
     *
     * @param string $schoolYear the school year in "YYYY-YYYY" format
     *
     * @return int the number of objectives in that course
     */
    public function countForSchoolYear(string $schoolYear): int
    {
        return $this->count(['schoolYear' => $schoolYear]);
    }

    /**
     * The most recent school year that has any objective, or null when none exist.
     *
     * @return string|null the latest school year in "YYYY-YYYY" format, or null
     */
    public function findLatestSchoolYear(): ?string
    {
        $latest = $this->createQueryBuilder('o')
            ->select('MAX(o.schoolYear)')
            ->getQuery()
            ->getSingleScalarResult();

        return null !== $latest ? (string) $latest : null;
    }

    /**
     * Next sequential number for a new objective (current maximum plus one; 1 when none exist). The
     * sequence is global across courses, so references (OBJ-NN) stay unique.
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

    /**
     * The objectives of a course marked as not achieved (PG-06.04), ordered by sequence. Used by the
     * auto-non-conformity engine, scoped to the course being evaluated so closed courses are not
     * reopened indefinitely.
     *
     * @param string $schoolYear the school year in "YYYY-YYYY" format
     *
     * @return Objective[] the unmet objectives of that course
     */
    public function findNotAchievedForSchoolYear(string $schoolYear): array
    {
        return $this->findBy(
            ['status' => ObjectiveStatus::NOT_ACHIEVED, 'schoolYear' => $schoolYear],
            ['sequence' => 'ASC'],
        );
    }
}
