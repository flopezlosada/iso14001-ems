<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create the supplier_incident table (PC.05 §5.6 supplier incidents).
 */
final class Version20260621200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the supplier_incident table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE supplier_incident (
                id INT AUTO_INCREMENT NOT NULL,
                supplier_id INT NOT NULL,
                occurred_on DATE NOT NULL,
                description LONGTEXT NOT NULL,
                resolution LONGTEXT DEFAULT NULL,
                severe TINYINT NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                INDEX idx_supplier_incident_supplier (supplier_id),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql('ALTER TABLE supplier_incident ADD CONSTRAINT fk_supplier_incident_supplier FOREIGN KEY (supplier_id) REFERENCES supplier (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE supplier_incident DROP FOREIGN KEY fk_supplier_incident_supplier');
        $this->addSql('DROP TABLE supplier_incident');
    }
}
