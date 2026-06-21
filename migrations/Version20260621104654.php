<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260621104654 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE app_user (id INT AUTO_INCREMENT NOT NULL, full_name VARCHAR(180) NOT NULL, email VARCHAR(180) NOT NULL, active TINYINT NOT NULL, UNIQUE INDEX UNIQ_88BDF3E9E7927C74 (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE user_role (user_id INT NOT NULL, role_id INT NOT NULL, INDEX IDX_2DE8C6A3A76ED395 (user_id), INDEX IDX_2DE8C6A3D60322AC (role_id), PRIMARY KEY (user_id, role_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE approval_event (id INT AUTO_INCREMENT NOT NULL, approved_at DATETIME NOT NULL, integrity_hash VARCHAR(128) NOT NULL, signed_pdf_path VARCHAR(1024) DEFAULT NULL, document_version_id INT NOT NULL, approver_id INT NOT NULL, INDEX IDX_BCF2554EA7F8C53 (document_version_id), INDEX IDX_BCF2554BB23766C (approver_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE document (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, type VARCHAR(255) NOT NULL, code VARCHAR(50) DEFAULT NULL, legacy_codes JSON NOT NULL, process VARCHAR(120) DEFAULT NULL, retention_years INT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, responsible_role_id INT DEFAULT NULL, INDEX IDX_D8698A76CCC0E8A1 (responsible_role_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE document_version (id INT AUTO_INCREMENT NOT NULL, revision_number INT NOT NULL, issue_date DATE NOT NULL, status VARCHAR(255) NOT NULL, author VARCHAR(180) DEFAULT NULL, change_summary LONGTEXT DEFAULT NULL, storage_path VARCHAR(1024) DEFAULT NULL, created_at DATETIME NOT NULL, document_id INT NOT NULL, INDEX IDX_1B73751FC33F7837 (document_id), UNIQUE INDEX uniq_document_revision (document_id, revision_number), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE role (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(50) NOT NULL, name VARCHAR(120) NOT NULL, description LONGTEXT DEFAULT NULL, UNIQUE INDEX UNIQ_57698A6A77153098 (code), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE scheduled_alert (id INT AUTO_INCREMENT NOT NULL, frequency VARCHAR(255) NOT NULL, next_due_date DATE NOT NULL, escalation_days INT DEFAULT NULL, last_notified_at DATETIME DEFAULT NULL, document_id INT NOT NULL, INDEX IDX_55801FEAC33F7837 (document_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE scheduled_alert_recipient_role (scheduled_alert_id INT NOT NULL, role_id INT NOT NULL, INDEX IDX_27BE0DCCE2D61DAD (scheduled_alert_id), INDEX IDX_27BE0DCCD60322AC (role_id), PRIMARY KEY (scheduled_alert_id, role_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE user_role ADD CONSTRAINT FK_2DE8C6A3A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_role ADD CONSTRAINT FK_2DE8C6A3D60322AC FOREIGN KEY (role_id) REFERENCES role (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE approval_event ADD CONSTRAINT FK_BCF2554EA7F8C53 FOREIGN KEY (document_version_id) REFERENCES document_version (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE approval_event ADD CONSTRAINT FK_BCF2554BB23766C FOREIGN KEY (approver_id) REFERENCES app_user (id)');
        $this->addSql('ALTER TABLE document ADD CONSTRAINT FK_D8698A76CCC0E8A1 FOREIGN KEY (responsible_role_id) REFERENCES role (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE document_version ADD CONSTRAINT FK_1B73751FC33F7837 FOREIGN KEY (document_id) REFERENCES document (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE scheduled_alert ADD CONSTRAINT FK_55801FEAC33F7837 FOREIGN KEY (document_id) REFERENCES document (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE scheduled_alert_recipient_role ADD CONSTRAINT FK_27BE0DCCE2D61DAD FOREIGN KEY (scheduled_alert_id) REFERENCES scheduled_alert (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE scheduled_alert_recipient_role ADD CONSTRAINT FK_27BE0DCCD60322AC FOREIGN KEY (role_id) REFERENCES role (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user_role DROP FOREIGN KEY FK_2DE8C6A3A76ED395');
        $this->addSql('ALTER TABLE user_role DROP FOREIGN KEY FK_2DE8C6A3D60322AC');
        $this->addSql('ALTER TABLE approval_event DROP FOREIGN KEY FK_BCF2554EA7F8C53');
        $this->addSql('ALTER TABLE approval_event DROP FOREIGN KEY FK_BCF2554BB23766C');
        $this->addSql('ALTER TABLE document DROP FOREIGN KEY FK_D8698A76CCC0E8A1');
        $this->addSql('ALTER TABLE document_version DROP FOREIGN KEY FK_1B73751FC33F7837');
        $this->addSql('ALTER TABLE scheduled_alert DROP FOREIGN KEY FK_55801FEAC33F7837');
        $this->addSql('ALTER TABLE scheduled_alert_recipient_role DROP FOREIGN KEY FK_27BE0DCCE2D61DAD');
        $this->addSql('ALTER TABLE scheduled_alert_recipient_role DROP FOREIGN KEY FK_27BE0DCCD60322AC');
        $this->addSql('DROP TABLE app_user');
        $this->addSql('DROP TABLE user_role');
        $this->addSql('DROP TABLE approval_event');
        $this->addSql('DROP TABLE document');
        $this->addSql('DROP TABLE document_version');
        $this->addSql('DROP TABLE role');
        $this->addSql('DROP TABLE scheduled_alert');
        $this->addSql('DROP TABLE scheduled_alert_recipient_role');
    }
}
