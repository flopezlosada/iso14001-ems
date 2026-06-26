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
}
