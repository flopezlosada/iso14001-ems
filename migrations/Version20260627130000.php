<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Creates the environmental communications register (RG-07.04.00, procedure PC.04.0,
 * ISO 14001:2015 §7.4): one row per internal/external communication, query, suggestion or complaint,
 * optionally linked to an interested party (the link is set to NULL, not cascaded, when the party
 * row is deleted, so the historical log is preserved).
 */
final class Version20260627130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create communication table (RG-07.04.00, ISO 14001 §7.4)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE communication (
                id INT AUTO_INCREMENT NOT NULL,
                interested_party_id INT DEFAULT NULL,
                occurred_on DATE NOT NULL,
                scope VARCHAR(20) NOT NULL,
                category VARCHAR(20) NOT NULL,
                channel VARCHAR(20) NOT NULL,
                subject VARCHAR(255) NOT NULL,
                details LONGTEXT DEFAULT NULL,
                sender VARCHAR(255) DEFAULT NULL,
                recipient VARCHAR(255) DEFAULT NULL,
                response LONGTEXT DEFAULT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                INDEX IDX_F9AFB5EB53295225 (interested_party_id),
                INDEX idx_communication_occurred_on (occurred_on),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
            SQL);
        $this->addSql('ALTER TABLE communication ADD CONSTRAINT FK_F9AFB5EB53295225 FOREIGN KEY (interested_party_id) REFERENCES interested_party (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE communication DROP FOREIGN KEY FK_F9AFB5EB53295225');
        $this->addSql('DROP TABLE communication');
    }
}
