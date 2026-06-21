<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\TrainingAction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TrainingAction>
 */
class TrainingActionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TrainingAction::class);
    }

    /**
     * Returns every training action of a given plan year, ordered by planned date, ready to
     * render the yearly training plan table.
     *
     * @param int $year the plan year
     *
     * @return TrainingAction[] the year's training actions
     */
    public function findForYear(int $year): array
    {
        return $this->findBy(
            ['planYear' => $year],
            ['plannedDate' => 'ASC', 'id' => 'ASC'],
        );
    }
}
