<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\TrainingAction;
use App\Entity\TrainingEvidence;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TrainingEvidence>
 */
class TrainingEvidenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TrainingEvidence::class);
    }

    /**
     * Returns every training evidence, newest first, ready to render the evidence log. The optional
     * linked training action is eager-loaded (LEFT JOIN) so rendering its description in the listing
     * does not trigger an N+1 query.
     *
     * @return TrainingEvidence[] the evidences ordered by training date, most recent first
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('e')
            ->addSelect('a')
            ->leftJoin('e.trainingAction', 'a')
            ->orderBy('e.trainingDate', 'DESC')
            ->addOrderBy('e.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Evidences linked to a given training action, newest first, for its detail page. The link is
     * optional (evidence can exist without a plan action), so this only returns the ones explicitly
     * tied to it.
     *
     * @return TrainingEvidence[] the linked evidences, most recent first
     */
    public function findByTrainingAction(TrainingAction $action): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.trainingAction = :action')
            ->setParameter('action', $action)
            ->orderBy('e.trainingDate', 'DESC')
            ->addOrderBy('e.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
