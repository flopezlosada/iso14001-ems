<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260621122552 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create audit_log table for the append-only activity trail (ISO 14001 7.5).';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE audit_log (id INT AUTO_INCREMENT NOT NULL, occurred_at DATETIME NOT NULL, actor VARCHAR(180) DEFAULT NULL, action VARCHAR(100) NOT NULL, subject_type VARCHAR(100) DEFAULT NULL, subject_id VARCHAR(64) DEFAULT NULL, summary LONGTEXT DEFAULT NULL, INDEX idx_audit_occurred_at (occurred_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE audit_log');
    }
}
