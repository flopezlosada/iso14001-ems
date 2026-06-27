<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\DafoAnalysis;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DafoAnalysis>
 */
class DafoAnalysisRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DafoAnalysis::class);
    }

    /**
     * All analyses with the most recent school year first, ready to render the list.
     *
     * @return DafoAnalysis[] every analysis ordered by school year (descending)
     */
    public function findAllOrdered(): array
    {
        return $this->findBy([], ['schoolYear' => 'DESC']);
    }

    /**
     * Whether an analysis already exists for a school year. Used to decide if the "clone to next
     * year" action has a free target, so cloning never overwrites an existing year.
     *
     * @param string $schoolYear the school year in "YYYY-YYYY" format
     *
     * @return bool true if an analysis is already registered for that school year
     */
    public function existsForSchoolYear(string $schoolYear): bool
    {
        return $this->count(['schoolYear' => $schoolYear]) > 0;
    }
}
