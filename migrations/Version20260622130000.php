<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds the document lifecycle state (active / cancelled / archived) and its reason, so a document
 * created by mistake or no longer applicable can be cancelled/archived with a trace instead of
 * being deleted (PC.01.0, append-only).
 */
final class Version20260622130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add document.lifecycle and document.lifecycle_reason';
    }

    public function up(Schema $schema): void
    {
        // Add with a default so existing rows become ACTIVE, then drop the default to match the
        // entity (a PHP-default enum column, no DB default) — same pattern as document.status.
        $this->addSql("ALTER TABLE document ADD lifecycle VARCHAR(20) DEFAULT 'active' NOT NULL, ADD lifecycle_reason LONGTEXT DEFAULT NULL");
        $this->addSql('ALTER TABLE document ALTER lifecycle DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE document DROP lifecycle, DROP lifecycle_reason');
    }
}
