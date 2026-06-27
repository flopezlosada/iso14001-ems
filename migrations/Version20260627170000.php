<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Creates the environmental training evidence register (ISO 14001:2015 §7.2/§7.3): one row per
 * person and training received (name, training, date, questionnaire), optionally linked to a planned
 * training action (the link is set to NULL, not cascaded, when the action row is deleted, so the
 * evidence log is preserved).
 */
final class Version20260627170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create training_evidence table (ISO 14001 §7.2/§7.3 training evidence register)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE training_evidence (
                id INT AUTO_INCREMENT NOT NULL,
                training_action_id INT DEFAULT NULL,
                person_name VARCHAR(255) NOT NULL,
                training_description VARCHAR(255) NOT NULL,
                training_date DATE NOT NULL,
                questionnaire_completed TINYINT(1) NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                INDEX IDX_EB044487F3BE93C1 (training_action_id),
                INDEX idx_training_evidence_date (training_date),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
            SQL);
        $this->addSql('ALTER TABLE training_evidence ADD CONSTRAINT FK_EB044487F3BE93C1 FOREIGN KEY (training_action_id) REFERENCES training_action (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE training_evidence DROP FOREIGN KEY FK_EB044487F3BE93C1');
        $this->addSql('DROP TABLE training_evidence');
    }
}
