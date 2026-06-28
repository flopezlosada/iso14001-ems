<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260628190102 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'risk_action.responsible: from free text to a nullable Role FK (SET NULL on role deletion).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE risk_action ADD responsible_id INT DEFAULT NULL, DROP responsible');
        $this->addSql('ALTER TABLE risk_action ADD CONSTRAINT FK_870782F6602AD315 FOREIGN KEY (responsible_id) REFERENCES role (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_870782F6602AD315 ON risk_action (responsible_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE risk_action DROP FOREIGN KEY FK_870782F6602AD315');
        $this->addSql('DROP INDEX IDX_870782F6602AD315 ON risk_action');
        $this->addSql('ALTER TABLE risk_action ADD responsible VARCHAR(255) DEFAULT NULL, DROP responsible_id');
    }
}
