<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\LegalRequirement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LegalRequirement>
 */
class LegalRequirementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LegalRequirement::class);
    }

    /**
     * All requirements ordered by their sequence, ready to render the list.
     *
     * @return LegalRequirement[] all requirements
     */
    public function findAllOrdered(): array
    {
        return $this->findBy([], ['sequence' => 'ASC']);
    }

    /**
     * Requirements whose next review is overdue or falls within the upcoming window, soonest first.
     * Drives the review reminder digest; requirements without a scheduled review are excluded.
     *
     * @param \DateTimeImmutable $on       the reference date (typically today)
     * @param int                $soonDays how many days ahead still count as "due"
     *
     * @return LegalRequirement[] requirements needing review attention
     */
    public function findDueForReview(\DateTimeImmutable $on, int $soonDays = 30): array
    {
        return $this->createQueryBuilder('lr')
            ->andWhere('lr.nextReviewOn IS NOT NULL')
            ->andWhere('lr.nextReviewOn <= :limit')
            ->setParameter('limit', $on->modify(sprintf('+%d days', $soonDays)))
            ->orderBy('lr.nextReviewOn', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Next sequential number for a new requirement (current maximum plus one; 1 when none exist).
     *
     * @return int the next sequence number (>= 1)
     */
    public function nextSequence(): int
    {
        $max = $this->createQueryBuilder('lr')
            ->select('MAX(lr.sequence)')
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $max + 1;
    }
}
