<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ManagementReview;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ManagementReview>
 */
class ManagementReviewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ManagementReview::class);
    }

    /**
     * All reviews, most recent course first, for the index.
     *
     * @return ManagementReview[] all management reviews
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('mr')
            ->orderBy('mr.exercise', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * The review for a given course, if any (used to prevent a duplicate for the same exercise).
     *
     * @param string $exercise the school year, e.g. "2025-2026"
     *
     * @return ManagementReview|null the review, or null if none exists for that course
     */
    public function findOneByExercise(string $exercise): ?ManagementReview
    {
        return $this->findOneBy(['exercise' => $exercise]);
    }

    /**
     * One review with its sections and participants eagerly fetched, for the detail/edit page
     * (avoids an N+1 over the sections).
     *
     * @param int $id the review id
     *
     * @return ManagementReview|null the review, or null if not found
     */
    public function findWithSections(int $id): ?ManagementReview
    {
        return $this->createQueryBuilder('mr')
            ->addSelect('s', 'p')
            ->leftJoin('mr.sections', 's')
            ->leftJoin('mr.participants', 'p')
            ->where('mr.id = :id')
            ->setParameter('id', $id)
            ->orderBy('s.position', 'ASC')
            ->getQuery()
            ->getOneOrNullResult();
    }
}
