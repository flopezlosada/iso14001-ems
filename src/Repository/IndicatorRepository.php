<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Indicator;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Indicator>
 */
class IndicatorRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Indicator::class);
    }

    /**
     * All indicators ordered by process and then name, ready to render the list.
     *
     * @return Indicator[] all indicators
     */
    public function findAllOrdered(): array
    {
        return $this->findBy([], ['process' => 'ASC', 'name' => 'ASC']);
    }
}
