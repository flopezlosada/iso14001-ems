<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ProcessArea;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProcessArea>
 */
class ProcessAreaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProcessArea::class);
    }

    /**
     * All areas in alphabetical order, ready for the management listing.
     *
     * @return ProcessArea[] all areas
     */
    public function findAllOrdered(): array
    {
        return $this->findBy([], ['name' => 'ASC']);
    }

    /**
     * Active areas only, alphabetical, to populate the choice widget when assigning an area.
     *
     * @return ProcessArea[] the active areas
     */
    public function findActiveOrdered(): array
    {
        return $this->findBy(['active' => true], ['name' => 'ASC']);
    }
}
