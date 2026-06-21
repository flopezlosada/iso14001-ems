<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create the non_conformity table (control list F.11.0 / report F.10.0).
 */
final class Version20260621160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the non_conformity table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE non_conformity (
                id INT AUTO_INCREMENT NOT NULL,
                reference VARCHAR(30) NOT NULL,
                origin VARCHAR(30) NOT NULL,
                origin_detail VARCHAR(255) DEFAULT NULL,
                reference_year INT NOT NULL,
                sequence INT NOT NULL,
                iso_clause VARCHAR(30) DEFAULT NULL,
                affected_process VARCHAR(20) DEFAULT NULL,
                description LONGTEXT NOT NULL,
                root_cause LONGTEXT DEFAULT NULL,
                responsible_id INT DEFAULT NULL,
                status VARCHAR(20) NOT NULL,
                opened_at DATE NOT NULL,
                closed_at DATE DEFAULT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE INDEX uniq_nc_reference (reference),
                UNIQUE INDEX uniq_nc_origin_year_sequence (origin, reference_year, sequence),
                INDEX idx_nc_responsible (responsible_id),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql('ALTER TABLE non_conformity ADD CONSTRAINT fk_nc_responsible FOREIGN KEY (responsible_id) REFERENCES app_user (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE non_conformity DROP FOREIGN KEY fk_nc_responsible');
        $this->addSql('DROP TABLE non_conformity');
    }
}
