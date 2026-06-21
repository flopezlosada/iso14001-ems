<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create the indicator and indicator_measurement tables (performance indicators, F.09.0).
 */
final class Version20260621235800 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the indicator and indicator_measurement tables.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE indicator (
                id INT AUTO_INCREMENT NOT NULL,
                name VARCHAR(255) NOT NULL,
                measurement_description LONGTEXT DEFAULT NULL,
                reference_value VARCHAR(120) DEFAULT NULL,
                process VARCHAR(30) NOT NULL,
                periodicity VARCHAR(20) NOT NULL,
                active TINYINT NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE indicator_measurement (
                id INT AUTO_INCREMENT NOT NULL,
                indicator_id INT NOT NULL,
                period_year INT NOT NULL,
                period_month INT NOT NULL,
                value NUMERIC(14, 3) NOT NULL,
                breached TINYINT NOT NULL,
                notes LONGTEXT DEFAULT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE INDEX uniq_indicator_period (indicator_id, period_year, period_month),
                INDEX idx_indicator_measurement_indicator (indicator_id),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql('ALTER TABLE indicator_measurement ADD CONSTRAINT fk_indicator_measurement_indicator FOREIGN KEY (indicator_id) REFERENCES indicator (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE indicator_measurement DROP FOREIGN KEY fk_indicator_measurement_indicator');
        $this->addSql('DROP TABLE indicator_measurement');
        $this->addSql('DROP TABLE indicator');
    }
}
