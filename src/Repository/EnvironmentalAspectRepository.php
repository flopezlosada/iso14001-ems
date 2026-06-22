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

    /**
     * Active aspects linked to a consumption source, i.e. the ones whose intensity can be estimated
     * and watched automatically. Used to build the cockpit's "aspectos a vigilar" list.
     *
     * @return EnvironmentalAspect[] the linked, active aspects ordered by name
     */
    public function findLinkedToConsumption(): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.active = true')
            ->andWhere('a.linkedConsumptionType IS NOT NULL')
            ->orderBy('a.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
