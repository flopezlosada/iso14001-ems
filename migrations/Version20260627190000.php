<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds the centre's own per-course code (source_code) to the objectives register (F.07.01). The
 * code restarts each course ("OBJ.01" appears once per year), so it is only unique within a school
 * year: the unique index is on (school_year, source_code). It is nullable because objectives created
 * from the UI carry only the globally unique surrogate reference; it is populated by the historical
 * ETL so the import can upsert by (school year, source code) without colliding the same code across
 * courses. MySQL allows multiple NULLs in a unique index, so UI rows do not clash.
 */
final class Version20260627190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add source_code to objective (per-course code, unique within a school year)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE objective ADD source_code VARCHAR(20) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_objective_year_source ON objective (school_year, source_code)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_objective_year_source ON objective');
        $this->addSql('ALTER TABLE objective DROP source_code');
    }
}
