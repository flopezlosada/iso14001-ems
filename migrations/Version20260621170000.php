<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create the corrective_action table (the action plan / PAC of a non-conformity).
 */
final class Version20260621170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the corrective_action table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE corrective_action (
                id INT AUTO_INCREMENT NOT NULL,
                non_conformity_id INT NOT NULL,
                sequence INT NOT NULL,
                description LONGTEXT NOT NULL,
                responsible_id INT DEFAULT NULL,
                planned_date DATE DEFAULT NULL,
                implementation_evidence LONGTEXT DEFAULT NULL,
                requires_direction_authorization TINYINT NOT NULL,
                authorized_by_id INT DEFAULT NULL,
                authorized_at DATETIME DEFAULT NULL,
                reviewed_by_id INT DEFAULT NULL,
                reviewed_at DATE DEFAULT NULL,
                efficacy VARCHAR(10) DEFAULT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE INDEX uniq_ca_nc_sequence (non_conformity_id, sequence),
                INDEX idx_ca_responsible (responsible_id),
                INDEX idx_ca_authorized_by (authorized_by_id),
                INDEX idx_ca_reviewed_by (reviewed_by_id),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql('ALTER TABLE corrective_action ADD CONSTRAINT fk_ca_non_conformity FOREIGN KEY (non_conformity_id) REFERENCES non_conformity (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE corrective_action ADD CONSTRAINT fk_ca_responsible FOREIGN KEY (responsible_id) REFERENCES app_user (id)');
        $this->addSql('ALTER TABLE corrective_action ADD CONSTRAINT fk_ca_authorized_by FOREIGN KEY (authorized_by_id) REFERENCES app_user (id)');
        $this->addSql('ALTER TABLE corrective_action ADD CONSTRAINT fk_ca_reviewed_by FOREIGN KEY (reviewed_by_id) REFERENCES app_user (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE corrective_action DROP FOREIGN KEY fk_ca_non_conformity');
        $this->addSql('ALTER TABLE corrective_action DROP FOREIGN KEY fk_ca_responsible');
        $this->addSql('ALTER TABLE corrective_action DROP FOREIGN KEY fk_ca_authorized_by');
        $this->addSql('ALTER TABLE corrective_action DROP FOREIGN KEY fk_ca_reviewed_by');
        $this->addSql('DROP TABLE corrective_action');
    }
}
