<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\OperationalControlItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OperationalControlItem>
 */
class OperationalControlItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OperationalControlItem::class);
    }

    /**
     * Active catalogue items ordered by their (global) position value. Grouping by section in the
     * enum's declaration order is the caller's responsibility (see
     * {@see \App\Controller\OperationalControlController::orderedActiveItems()}): SQL cannot sort by
     * the enum's declaration order, and position is global, not per-section.
     *
     * @return OperationalControlItem[] the active items, ordered by position
     */
    public function findActiveOrdered(): array
    {
        return $this->findBy(['active' => true], ['position' => 'ASC']);
    }

    /**
     * Every catalogue item (active and inactive), ordered by section and position, for the admin
     * management screen.
     *
     * @return OperationalControlItem[] all items
     */
    public function findAllOrdered(): array
    {
        return $this->findBy([], ['section' => 'ASC', 'position' => 'ASC']);
    }
}
