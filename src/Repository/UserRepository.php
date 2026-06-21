<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Finds an active user by e-mail, or null if there is no active user with that address.
     * Only registered (allow-listed) users can sign in, so an unknown e-mail returns null.
     *
     * @param string $email the e-mail address
     *
     * @return User|null the matching active user, or null
     */
    public function findActiveByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => strtolower(trim($email)), 'active' => true]);
    }
}
