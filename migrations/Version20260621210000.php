<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create the legal_requirement table (list of legal requirements and compliance, PC-06.03).
 */
final class Version20260621210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the legal_requirement table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE legal_requirement (
                id INT AUTO_INCREMENT NOT NULL,
                reference VARCHAR(20) NOT NULL,
                sequence INT NOT NULL,
                legal_provision VARCHAR(500) NOT NULL,
                scope VARCHAR(20) NOT NULL,
                environmental_vector VARCHAR(255) DEFAULT NULL,
                specific_requirement LONGTEXT NOT NULL,
                compliance_evidence LONGTEXT DEFAULT NULL,
                compliance_status VARCHAR(20) NOT NULL,
                evaluation_frequency VARCHAR(20) DEFAULT NULL,
                last_reviewed_on DATE DEFAULT NULL,
                next_review_on DATE DEFAULT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE INDEX uniq_legal_requirement_reference (reference),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE legal_requirement');
    }
}
