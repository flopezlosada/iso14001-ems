<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\InterestedParty;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InterestedParty>
 */
class InterestedPartyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InterestedParty::class);
    }

    /**
     * Returns every interested party recorded for a given review year, ordered by name, ready to
     * render the year's PPI table.
     *
     * @param int $year the review year
     *
     * @return InterestedParty[] the year's interested parties
     */
    public function findForYear(int $year): array
    {
        return $this->findBy(
            ['reviewYear' => $year],
            ['name' => 'ASC', 'id' => 'ASC'],
        );
    }
}
