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
     * Active aspects with an auto-intensity source — a linked consumption utility or a set of LER
     * codes (waste) — i.e. the ones whose intensity can be estimated and watched automatically. Used
     * to build the cockpit's "aspectos a vigilar" list. The LER-codes condition is applied in PHP to
     * avoid JSON SQL; the active set is small.
     *
     * @return EnvironmentalAspect[] the linked, active aspects ordered by name
     */
    public function findLinkedForIntensity(): array
    {
        $active = $this->findBy(['active' => true], ['name' => 'ASC']);

        return array_values(array_filter(
            $active,
            static fn (EnvironmentalAspect $aspect): bool => null !== $aspect->getLinkedConsumptionType() || [] !== $aspect->getLinkedLerCodes(),
        ));
    }
}
