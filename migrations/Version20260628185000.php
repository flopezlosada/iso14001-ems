<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Records the review step of a document revision (PC.01.0: elaboración → revisión → aprobación).
 */
final class Version20260628185000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add reviewed_by / reviewed_at to a document revision (review step before approval).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE document_version ADD reviewed_by VARCHAR(180) DEFAULT NULL, ADD reviewed_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE document_version DROP reviewed_by, DROP reviewed_at');
    }
}
