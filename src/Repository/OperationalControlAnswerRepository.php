<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\OperationalControlAnswer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OperationalControlAnswer>
 */
class OperationalControlAnswerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OperationalControlAnswer::class);
    }
}
