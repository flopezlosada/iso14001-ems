<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\AuditLog;
use App\Repository\AuditLogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration tests for {@see AuditLogRepository::findForSubject()} over the real test database
 * (rolled back per test): it must return only the entries of the given subject, optionally narrowed
 * to one action, newest first. The obligation detail page relies on this to show period reviews.
 */
final class AuditLogRepositoryTest extends KernelTestCase
{
    private AuditLogRepository $logs;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->logs = $container->get(AuditLogRepository::class);
        $this->entityManager = $container->get(EntityManagerInterface::class);
    }

    private function persist(string $action, string $subjectType, string $subjectId, ?string $summary = null, ?string $occurredAt = null): void
    {
        $log = new AuditLog($action, 'tester', $subjectType, $subjectId, $summary);
        if (null !== $occurredAt) {
            // occurredAt is set in the constructor (immutable, no setter) — pin it explicitly here so
            // the ordering assertion does not depend on NOW() granularity between fast inserts.
            $property = new \ReflectionProperty(AuditLog::class, 'occurredAt');
            $property->setValue($log, new \DateTimeImmutable($occurredAt));
        }
        $this->entityManager->persist($log);
    }

    public function testFindForSubjectNarrowsByActionAndReturnsNewestFirst(): void
    {
        $this->persist('obligation.completed', 'Document', '7', 'cierre 1', '2026-03-10 09:00:00');
        $this->persist('document.revision_approved', 'Document', '7', 'aprobada', '2026-04-01 09:00:00'); // mismo doc, otra acción
        $this->persist('obligation.completed', 'Document', '7', 'cierre 2', '2026-09-10 09:00:00');
        $this->persist('obligation.completed', 'Document', '99', 'otro doc', '2026-09-11 09:00:00');       // otra obligación
        $this->entityManager->flush();

        $result = $this->logs->findForSubject('Document', '7', 'obligation.completed');

        self::assertCount(2, $result);
        // Newest first (occurredAt desc, id desc as tie-break): the later insert leads.
        self::assertSame('cierre 2', $result[0]->getSummary());
        self::assertSame('cierre 1', $result[1]->getSummary());
    }

    public function testFindForSubjectWithoutActionReturnsEveryEntryOfThatSubject(): void
    {
        $this->persist('obligation.completed', 'Document', '7');
        $this->persist('document.revision_approved', 'Document', '7');
        $this->persist('obligation.completed', 'Document', '8');
        $this->entityManager->flush();

        self::assertCount(2, $this->logs->findForSubject('Document', '7'));
    }
}
