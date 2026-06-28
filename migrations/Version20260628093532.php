<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260628093532 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'training_action: make type/planned_date nullable and add needs_review + review_note (import flags non-normalizable rows for manual review instead of quarantining them)';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE training_action ADD needs_review TINYINT DEFAULT 0 NOT NULL, ADD review_note LONGTEXT DEFAULT NULL, CHANGE type type VARCHAR(255) DEFAULT NULL, CHANGE planned_date planned_date DATE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // Rows imported "pending review" hold NULL in type/planned_date, which the pre-migration
        // NOT NULL schema cannot represent. Reverting therefore drops those rows first: a plain
        // CHANGE ... NOT NULL would otherwise fail mid-rollback ("Column cannot be null").
        $this->addSql('DELETE FROM training_action WHERE type IS NULL OR planned_date IS NULL');
        $this->addSql('ALTER TABLE training_action DROP needs_review, DROP review_note, CHANGE type type VARCHAR(255) NOT NULL, CHANGE planned_date planned_date DATE NOT NULL');
    }
}
