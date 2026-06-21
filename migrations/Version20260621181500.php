<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create the training_action table (the annual training plan, form F.03.0).
 */
final class Version20260621181500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the training_action table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE training_action (
                id INT AUTO_INCREMENT NOT NULL,
                plan_year INT NOT NULL,
                description VARCHAR(255) NOT NULL,
                type VARCHAR(255) NOT NULL,
                target_audience VARCHAR(255) NOT NULL,
                objectives LONGTEXT NOT NULL,
                planned_date DATE NOT NULL,
                methodology LONGTEXT NOT NULL,
                actual_date DATE DEFAULT NULL,
                efficacy_evaluation LONGTEXT DEFAULT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                INDEX idx_training_plan_year (plan_year),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE training_action');
    }
}
