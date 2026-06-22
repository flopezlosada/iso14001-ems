<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Relaxes the waste_record columns that the real historical register often leaves empty or
 * unparseable (lost/mangled LER codes, non-kg amounts, free-text dates, missing manager) so the
 * 3-year register can be imported without discarding most of it. The original wording of any
 * non-structured value is preserved in the existing notes column by the importer.
 */
final class Version20260622120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make waste_record ler_code, quantity_kg, pickup_date and manager nullable';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE waste_record '
            .'ADD source_hash VARCHAR(64) DEFAULT NULL, '
            .'CHANGE ler_code ler_code VARCHAR(20) DEFAULT NULL, '
            .'CHANGE quantity_kg quantity_kg NUMERIC(12, 3) DEFAULT NULL, '
            .'CHANGE pickup_date pickup_date DATE DEFAULT NULL COMMENT \'(DC2Type:date_immutable)\', '
            .'CHANGE manager manager VARCHAR(255) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_waste_source_hash ON waste_record (source_hash)');
    }

    public function down(Schema $schema): void
    {
        // One-way cutover migration: once the historical register is imported, waste_record holds
        // NULLs in these columns, so re-imposing NOT NULL will fail (MySQL strict mode) rather than
        // corrupt data. To revert, first delete the imported rows: DELETE FROM waste_record WHERE
        // source_hash IS NOT NULL.
        $this->addSql('DROP INDEX uniq_waste_source_hash ON waste_record');
        $this->addSql('ALTER TABLE waste_record '
            .'DROP source_hash, '
            .'CHANGE ler_code ler_code VARCHAR(20) NOT NULL, '
            .'CHANGE quantity_kg quantity_kg NUMERIC(12, 3) NOT NULL, '
            .'CHANGE pickup_date pickup_date DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\', '
            .'CHANGE manager manager VARCHAR(255) NOT NULL');
    }
}
