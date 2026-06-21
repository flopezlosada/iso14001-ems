<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\EnvironmentalAspect;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EnvironmentalAspect>
 */
class EnvironmentalAspectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EnvironmentalAspect::class);
    }

    /**
     * All aspects ordered by category and then name, ready to render the evaluation list.
     *
     * @return EnvironmentalAspect[] all aspects
     */
    public function findAllOrdered(): array
    {
        return $this->findBy([], ['category' => 'ASC', 'name' => 'ASC']);
    }
}
