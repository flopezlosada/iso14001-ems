<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ScheduledAlert;
use App\Enum\AlertFrequency;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ScheduledAlert>
 */
class ScheduledAlertRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ScheduledAlert::class);
    }

    /**
     * Fixed-cadence alerts that are due on or before the given date, with their document and
     * recipient roles eager-loaded to avoid an N+1 while building the e-mails. This is a coarse
     * pre-filter (due, non event-driven); the precise "owes a reminder this cycle" decision is
     * {@see ScheduledAlert::needsNotification()}, kept as the single source of truth.
     *
     * @param \DateTimeImmutable $on reference date (today)
     *
     * @return ScheduledAlert[] the alerts to evaluate for notification
     */
    public function findDueCandidates(\DateTimeImmutable $on): array
    {
        return $this->createQueryBuilder('a')
            ->addSelect('d', 'rr')
            ->join('a.document', 'd')
            ->leftJoin('a.recipientRoles', 'rr')
            ->where('a.frequency != :event')
            ->andWhere('a.nextDueDate <= :on')
            ->setParameter('event', AlertFrequency::ON_EVENT->value)
            ->setParameter('on', $on->format('Y-m-d'))
            ->orderBy('a.nextDueDate', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
