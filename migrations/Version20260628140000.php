<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds a composite index on audit_log (subject_type, subject_id, occurred_at) to back
 * AuditLogRepository::findForSubject(), used by the obligation detail page to list a document's
 * period-review closures newest-first without scanning the whole log.
 */
final class Version20260628140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'audit_log: índice compuesto (subject_type, subject_id, occurred_at) para findForSubject';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX idx_audit_subject ON audit_log (subject_type, subject_id, occurred_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_audit_subject ON audit_log');
    }
}
