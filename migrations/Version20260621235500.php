<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create the objective table (environmental objectives, PG-06.04 / F.07.01).
 */
final class Version20260621235500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the objective table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE objective (
                id INT AUTO_INCREMENT NOT NULL,
                reference VARCHAR(20) NOT NULL,
                sequence INT NOT NULL,
                description LONGTEXT NOT NULL,
                responsible_id INT DEFAULT NULL,
                related_aspect_id INT DEFAULT NULL,
                target_period VARCHAR(120) DEFAULT NULL,
                status VARCHAR(20) NOT NULL,
                last_reviewed_on DATE DEFAULT NULL,
                notes LONGTEXT DEFAULT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE INDEX uniq_objective_reference (reference),
                INDEX idx_objective_responsible (responsible_id),
                INDEX idx_objective_aspect (related_aspect_id),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql('ALTER TABLE objective ADD CONSTRAINT fk_objective_responsible FOREIGN KEY (responsible_id) REFERENCES app_user (id)');
        $this->addSql('ALTER TABLE objective ADD CONSTRAINT fk_objective_aspect FOREIGN KEY (related_aspect_id) REFERENCES environmental_aspect (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE objective DROP FOREIGN KEY fk_objective_responsible');
        $this->addSql('ALTER TABLE objective DROP FOREIGN KEY fk_objective_aspect');
        $this->addSql('DROP TABLE objective');
    }
}
