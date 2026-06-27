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

    /**
     * Finds the training action by its natural key during the real data import: (plan year,
     * description, target audience). The F.03.0 sheet has no code or id, and the same course
     * description recurs within a year for different audiences (e.g. "curso iso 14001" is delivered
     * to "profesorado", to "personal no docente" and to "profesorado de nueva incorporación" in the
     * same year), so the audience is needed to keep those distinct actions from collapsing into one
     * on an idempotent upsert.
     *
     * @param int    $year           the plan year
     * @param string $description    the course description ("Descripción del curso")
     * @param string $targetAudience the roles/staff the action is aimed at
     *
     * @return TrainingAction|null the matching action, or null when none exists yet
     */
    public function findOneByNaturalKey(int $year, string $description, string $targetAudience): ?TrainingAction
    {
        return $this->findOneBy([
            'planYear' => $year,
            'description' => $description,
            'targetAudience' => $targetAudience,
        ]);
    }
}
