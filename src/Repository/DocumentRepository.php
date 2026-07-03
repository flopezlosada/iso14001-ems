<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Document;
use App\Enum\DocumentLifecycle;
use App\Enum\VersionStatus;
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

    /**
     * Every document in the register (the live F.01), including the ones with no ISO chapter
     * (manual, procedures) that the obligations cockpit does not show. Versions and responsible role
     * are eager-loaded so the list can show the in-force revision without an N+1 query. Ordered by
     * code here; the caller re-orders by the PC.01.0 type taxonomy (not the alphabetical enum value).
     *
     * @return Document[] all documents, ordered by code
     */
    public function findForRegister(): array
    {
        return $this->createQueryBuilder('d')
            ->addSelect('v', 'r')
            ->leftJoin('d.versions', 'v')
            ->leftJoin('d.responsibleRole', 'r')
            ->orderBy('d.code', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Resolves a set of in-force ISO codes to their documents in a single query, so help pages can
     * deep-link their referenced SGA documents without an N+1 lookup (one query per code). Matches
     * the current {@see Document::$code} of documents still ACTIVE (cancelled/archived ones are
     * excluded): the domain reuses codes over time, so filtering by lifecycle keeps the link pointing
     * at the version in force. Codes with no live document are simply absent from the map.
     *
     * @param list<string> $codes the ISO codes to resolve (e.g. ['PG-06.01', 'RG-06.01.01'])
     *
     * @return array<string, Document> the found documents indexed by their code
     */
    public function findByCodes(array $codes): array
    {
        if ([] === $codes) {
            return [];
        }

        /** @var list<Document> $documents */
        $documents = $this->createQueryBuilder('d')
            ->where('d.code IN (:codes)')
            ->andWhere('d.lifecycle = :lifecycle')
            ->setParameter('codes', $codes)
            ->setParameter('lifecycle', DocumentLifecycle::ACTIVE)
            ->getQuery()
            ->getResult();

        $byCode = [];
        foreach ($documents as $document) {
            $code = $document->getCode();
            if (null !== $code) {
                $byCode[$code] = $document;
            }
        }

        return $byCode;
    }

    /**
     * The ids of the documents that have a version in force (an approved revision). Returned as a
     * flat id list so a caller can tell, in a single query and without an N+1, whether an obligation
     * is backed by a live document — used to gate "marcar revisado" on the cockpit, which only makes
     * sense once the document is actually approved and in force (you cannot have reviewed a document
     * that does not yet exist).
     *
     * @return int[] the ids of documents with at least one approved version
     */
    public function findIdsWithVersionInForce(): array
    {
        $rows = $this->createQueryBuilder('d')
            ->select('DISTINCT d.id')
            ->innerJoin('d.versions', 'v')
            ->where('v.status = :approved')
            ->setParameter('approved', VersionStatus::APPROVED->value)
            ->getQuery()
            ->getScalarResult();

        return array_map(static fn (array $row): int => (int) $row['id'], $rows);
    }
}
