<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Document;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Document>
 */
class DocumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Document::class);
    }

    /**
     * The periodic obligations (those with an ISO chapter), with their review alerts and
     * responsible role eager-loaded so callers can classify urgency and group by phase without
     * triggering an N+1 query. Ordered by chapter then code, the centre's reading order.
     *
     * @return Document[] the obligations of the register
     */
    public function findObligations(): array
    {
        return $this->createQueryBuilder('d')
            ->addSelect('a', 'r')
            ->leftJoin('d.alerts', 'a')
            ->leftJoin('d.responsibleRole', 'r')
            ->where('d.isoChapter IS NOT NULL')
            ->orderBy('d.isoChapter', 'ASC')
            ->addOrderBy('d.code', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
