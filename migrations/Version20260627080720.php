<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Creates the management-system audit register (PC.09.0, ISO 14001:2015 §9.2): the system_audit
 * table and the optional link from a non-conformity to the audit it was raised in (FK SET NULL, so
 * deleting an audit keeps its non-conformities).
 */
final class Version20260627080720 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create system_audit and link non_conformity.audit_id to it';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE system_audit (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(20) NOT NULL, audit_year INT NOT NULL, conducted_on DATE DEFAULT NULL, auditor VARCHAR(255) NOT NULL, scope LONGTEXT DEFAULT NULL, objective LONGTEXT DEFAULT NULL, conclusions LONGTEXT DEFAULT NULL, report_path VARCHAR(255) DEFAULT NULL, report_original_name VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE non_conformity ADD audit_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE non_conformity ADD CONSTRAINT FK_9726A49ABD29F359 FOREIGN KEY (audit_id) REFERENCES system_audit (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_9726A49ABD29F359 ON non_conformity (audit_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE non_conformity DROP FOREIGN KEY FK_9726A49ABD29F359');
        $this->addSql('DROP INDEX IDX_9726A49ABD29F359 ON non_conformity');
        $this->addSql('ALTER TABLE non_conformity DROP audit_id');
        $this->addSql('DROP TABLE system_audit');
    }
}
