<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds the school year to the objectives register (F.07.01): objectives are redacted anew each
 * course (PG-06.04), so they are now grouped by course like the sibling annual registers and can be
 * cloned forward. Existing rows are backfilled to the current course (2025-2026) before the column
 * becomes required, so no data is lost.
 */
final class Version20260627180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add school_year to objective (annual grouping for clone-previous)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE objective ADD school_year VARCHAR(9) DEFAULT NULL');
        $this->addSql("UPDATE objective SET school_year = '2025-2026' WHERE school_year IS NULL");
        $this->addSql('ALTER TABLE objective CHANGE school_year school_year VARCHAR(9) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE objective DROP school_year');
    }
}
