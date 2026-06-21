<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create the emergency_drill table (drill reports RG-08.02.01 of procedure PG-08.02).
 */
final class Version20260621220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the emergency_drill table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE emergency_drill (
                id INT AUTO_INCREMENT NOT NULL,
                drill_date DATE NOT NULL,
                emergency_type VARCHAR(255) NOT NULL,
                location VARCHAR(255) NOT NULL,
                participants LONGTEXT NOT NULL,
                action_procedure LONGTEXT NOT NULL,
                conclusions LONGTEXT NOT NULL,
                reported_by VARCHAR(255) DEFAULT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                INDEX idx_emergency_drill_date (drill_date),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE emergency_drill');
    }
}
