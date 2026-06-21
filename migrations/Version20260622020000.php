<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Turn the document registry into the obligations backbone: add the ISO chapter (supra-structure),
 * the manual review status + note, the linked module (reusing the Area catalog) and the plain-text
 * instructions ("qué hacer") that replace the consultant's guidance.
 */
final class Version20260622020000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add obligation fields to document (iso_chapter, status, status_note, linked_area, instructions).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE document ADD iso_chapter INT DEFAULT NULL');
        // status is NOT NULL: add with a transient default so existing rows backfill to 'pending',
        // then drop the default to match the entity (which declares no DB default).
        $this->addSql("ALTER TABLE document ADD status VARCHAR(20) DEFAULT 'pending' NOT NULL");
        $this->addSql('ALTER TABLE document ALTER status DROP DEFAULT');
        $this->addSql('ALTER TABLE document ADD status_note LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE document ADD linked_area VARCHAR(30) DEFAULT NULL');
        $this->addSql('ALTER TABLE document ADD instructions LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE document DROP iso_chapter');
        $this->addSql('ALTER TABLE document DROP status');
        $this->addSql('ALTER TABLE document DROP status_note');
        $this->addSql('ALTER TABLE document DROP linked_area');
        $this->addSql('ALTER TABLE document DROP instructions');
    }
}
