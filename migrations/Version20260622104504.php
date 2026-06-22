<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Links a WASTE aspect to the LER codes whose waste feeds its auto-intensity (PG-06.01): adds
 * environmental_aspect.linked_ler_codes (JSON list of codes, empty by default).
 */
final class Version20260622104504 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add environmental_aspect.linked_ler_codes for waste auto-intensity';
    }

    public function up(Schema $schema): void
    {
        // Add nullable, backfill existing rows with an empty array, then enforce NOT NULL: a JSON
        // NOT NULL column cannot be added directly to a populated table without a default.
        $this->addSql('ALTER TABLE environmental_aspect ADD linked_ler_codes JSON DEFAULT NULL');
        $this->addSql("UPDATE environmental_aspect SET linked_ler_codes = '[]'");
        $this->addSql('ALTER TABLE environmental_aspect MODIFY linked_ler_codes JSON NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE environmental_aspect DROP linked_ler_codes');
    }
}
