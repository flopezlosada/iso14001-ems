<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create the risk_opportunity, risk_assessment, risk_action and process_area tables (PC.03.0 / F.08.0).
 */
final class Version20260622000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the risk and opportunity tables (PC.03.0 / F.08.0).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE process_area (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, active TINYINT NOT NULL, UNIQUE INDEX uniq_process_area_name (name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE risk_opportunity (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(20) NOT NULL, description LONGTEXT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, process_area_id INT NOT NULL, INDEX IDX_59E4CB86F5779A49 (process_area_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE risk_assessment (id INT AUTO_INCREMENT NOT NULL, exercise VARCHAR(9) NOT NULL, probability SMALLINT NOT NULL, impact SMALLINT NOT NULL, score SMALLINT NOT NULL, category VARCHAR(20) DEFAULT NULL, justification LONGTEXT DEFAULT NULL, revision_number SMALLINT NOT NULL, approved_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, risk_opportunity_id INT NOT NULL, approved_by_id INT DEFAULT NULL, INDEX IDX_FAE2BC1BC908ABF (risk_opportunity_id), INDEX IDX_FAE2BC1B2D234F6A (approved_by_id), UNIQUE INDEX uniq_risk_assessment_exercise (risk_opportunity_id, exercise), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE risk_action (id INT AUTO_INCREMENT NOT NULL, description LONGTEXT NOT NULL, responsible VARCHAR(255) DEFAULT NULL, deadline VARCHAR(255) DEFAULT NULL, efficacy LONGTEXT DEFAULT NULL, evaluated_at DATE DEFAULT NULL, assessment_id INT NOT NULL, INDEX IDX_870782F6DD3DD5F1 (assessment_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE risk_opportunity ADD CONSTRAINT FK_59E4CB86F5779A49 FOREIGN KEY (process_area_id) REFERENCES process_area (id)');
        $this->addSql('ALTER TABLE risk_assessment ADD CONSTRAINT FK_FAE2BC1BC908ABF FOREIGN KEY (risk_opportunity_id) REFERENCES risk_opportunity (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE risk_assessment ADD CONSTRAINT FK_FAE2BC1B2D234F6A FOREIGN KEY (approved_by_id) REFERENCES app_user (id)');
        $this->addSql('ALTER TABLE risk_action ADD CONSTRAINT FK_870782F6DD3DD5F1 FOREIGN KEY (assessment_id) REFERENCES risk_assessment (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE risk_action DROP FOREIGN KEY FK_870782F6DD3DD5F1');
        $this->addSql('ALTER TABLE risk_assessment DROP FOREIGN KEY FK_FAE2BC1BC908ABF');
        $this->addSql('ALTER TABLE risk_assessment DROP FOREIGN KEY FK_FAE2BC1B2D234F6A');
        $this->addSql('ALTER TABLE risk_opportunity DROP FOREIGN KEY FK_59E4CB86F5779A49');
        $this->addSql('DROP TABLE risk_action');
        $this->addSql('DROP TABLE risk_assessment');
        $this->addSql('DROP TABLE risk_opportunity');
        $this->addSql('DROP TABLE process_area');
    }
}
