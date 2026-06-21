<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create the environmental_aspect and aspect_evaluation tables (PG-06.01 / RG-06.01.01).
 */
final class Version20260621230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the environmental_aspect and aspect_evaluation tables.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE environmental_aspect (
                id INT AUTO_INCREMENT NOT NULL,
                name VARCHAR(255) NOT NULL,
                type VARCHAR(20) NOT NULL,
                category VARCHAR(20) DEFAULT NULL,
                unit VARCHAR(50) DEFAULT NULL,
                associated_impact VARCHAR(255) DEFAULT NULL,
                active TINYINT NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE aspect_evaluation (
                id INT AUTO_INCREMENT NOT NULL,
                aspect_id INT NOT NULL,
                evaluation_year INT NOT NULL,
                frequency SMALLINT DEFAULT NULL,
                intensity SMALLINT DEFAULT NULL,
                hazard SMALLINT DEFAULT NULL,
                significance_score INT NOT NULL,
                significant TINYINT NOT NULL,
                notes LONGTEXT DEFAULT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE INDEX uniq_aspect_eval_year (aspect_id, evaluation_year),
                INDEX idx_aspect_eval_aspect (aspect_id),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql('ALTER TABLE aspect_evaluation ADD CONSTRAINT fk_aspect_eval_aspect FOREIGN KEY (aspect_id) REFERENCES environmental_aspect (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE aspect_evaluation DROP FOREIGN KEY fk_aspect_eval_aspect');
        $this->addSql('DROP TABLE aspect_evaluation');
        $this->addSql('DROP TABLE environmental_aspect');
    }
}
