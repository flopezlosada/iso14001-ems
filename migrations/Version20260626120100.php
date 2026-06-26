<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create the dafo_analysis table (SWOT analysis of the environmental context, register F.06.0).
 * One row per school year, with the four quadrants as free text.
 */
final class Version20260626120100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the dafo_analysis table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE dafo_analysis (
                id INT AUTO_INCREMENT NOT NULL,
                school_year VARCHAR(9) NOT NULL,
                weaknesses LONGTEXT DEFAULT NULL,
                threats LONGTEXT DEFAULT NULL,
                strengths LONGTEXT DEFAULT NULL,
                opportunities LONGTEXT DEFAULT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE INDEX uniq_dafo_analysis_school_year (school_year),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE dafo_analysis');
    }
}
