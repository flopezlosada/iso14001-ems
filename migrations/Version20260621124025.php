<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260621124025 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add JSON permissions (per-area read/write) column to the role table.';
    }

    public function up(Schema $schema): void
    {
        // Two-step add so it works whether or not the table already has rows: add nullable,
        // backfill with an empty map, then enforce NOT NULL.
        $this->addSql('ALTER TABLE role ADD permissions JSON DEFAULT NULL');
        $this->addSql("UPDATE role SET permissions = '[]'");
        $this->addSql('ALTER TABLE role MODIFY permissions JSON NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE role DROP permissions');
    }
}
