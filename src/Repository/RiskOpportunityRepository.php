<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\RiskOpportunity;
use App\Enum\RiskOpportunityType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RiskOpportunity>
 */
class RiskOpportunityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RiskOpportunity::class);
    }

    /**
     * All items ordered by type then creation, with their area eagerly fetched to avoid N+1 when
     * rendering the list.
     *
     * @return RiskOpportunity[] all risks and opportunities
     */
    public function findAllOrdered(): array
    {
        // Eager-fetch area and assessments so the index can show the latest valuation without an
        // extra query per row (N+1).
        return $this->createQueryBuilder('ro')
            ->addSelect('area', 'a')
            ->leftJoin('ro.processArea', 'area')
            ->leftJoin('ro.assessments', 'a')
            ->orderBy('ro.type', 'ASC')
            ->addOrderBy('ro.createdAt', 'DESC')
            ->addOrderBy('a.exercise', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * One item with its area, assessments and their actions eagerly fetched, for the detail page
     * (avoids an N+1 over each assessment's actions).
     *
     * @param int $id the item id
     *
     * @return RiskOpportunity|null the item, or null if not found
     */
    public function findWithAssessmentsAndActions(int $id): ?RiskOpportunity
    {
        return $this->createQueryBuilder('ro')
            ->addSelect('area', 'a', 'act')
            ->leftJoin('ro.processArea', 'area')
            ->leftJoin('ro.assessments', 'a')
            ->leftJoin('a.actions', 'act')
            ->where('ro.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Items of a single type (risks or opportunities), area eagerly fetched.
     *
     * @param RiskOpportunityType $type the type to filter by
     *
     * @return RiskOpportunity[] the matching items
     */
    public function findByType(RiskOpportunityType $type): array
    {
        return $this->createQueryBuilder('ro')
            ->addSelect('area')
            ->leftJoin('ro.processArea', 'area')
            ->where('ro.type = :type')
            ->setParameter('type', $type)
            ->orderBy('ro.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
