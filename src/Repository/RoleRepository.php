<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Role;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Role>
 */
class RoleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Role::class);
    }

    /**
     * Returns all roles ordered by name.
     *
     * @return Role[]
     */
    public function findAllOrdered(): array
    {
        return $this->findBy([], ['name' => 'ASC']);
    }

    /**
     * The display names of the given roles, in a single query, keyed by role code. Codes with no
     * matching role are simply absent from the result.
     *
     * @param list<string> $codes the role codes to resolve
     *
     * @return array<string, string> role name by role code
     */
    public function findNamesByCode(array $codes): array
    {
        if ([] === $codes) {
            return [];
        }

        /** @var list<array{code: string, name: string}> $rows */
        $rows = $this->createQueryBuilder('r')
            ->select('r.code, r.name')
            ->where('r.code IN (:codes)')
            ->setParameter('codes', $codes)
            ->getQuery()
            ->getArrayResult();

        return array_column($rows, 'name', 'code');
    }
}
