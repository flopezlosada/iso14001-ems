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
