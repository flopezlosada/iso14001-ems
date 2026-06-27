<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Communication;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Communication>
 */
class CommunicationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Communication::class);
    }

    /**
     * Returns every communication, newest first, ready to render the register's listing.
     *
     * @return Communication[] all communications, ordered by date (and id) descending
     */
    public function findAllOrdered(): array
    {
        return $this->findBy([], ['occurredOn' => 'DESC', 'id' => 'DESC']);
    }

    /**
     * Returns the communications that took place within a calendar year, oldest first. Used by the
     * management review to summarise the year's interested-party communications and complaints.
     *
     * The interested party (if any) is eager-joined so the summary can read it without an extra
     * query per row (avoids N+1).
     *
     * @param int $year the calendar year (matched against the communication date)
     *
     * @return Communication[] the year's communications, ordered by date (and id) ascending
     */
    public function findForYear(int $year): array
    {
        return $this->createQueryBuilder('c')
            ->addSelect('p')
            ->leftJoin('c.interestedParty', 'p')
            ->where('c.occurredOn >= :start')
            ->andWhere('c.occurredOn < :nextStart')
            ->setParameter('start', new \DateTimeImmutable(sprintf('%d-01-01', $year)))
            ->setParameter('nextStart', new \DateTimeImmutable(sprintf('%d-01-01', $year + 1)))
            ->orderBy('c.occurredOn', 'ASC')
            ->addOrderBy('c.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
