<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Settings;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Settings>
 */
class SettingsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Settings::class);
    }

    /**
     * The single settings row, or null when none has been saved yet. Ordered by id so that, should a
     * stray second row ever exist, the result is deterministic (the original row wins).
     *
     * @return Settings|null the settings, or null
     */
    public function findSettings(): ?Settings
    {
        return $this->findOneBy([], ['id' => 'ASC']);
    }
}
