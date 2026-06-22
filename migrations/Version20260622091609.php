<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Business configuration editable from the UI: the `settings` singleton holding the aspect
 * significance thresholds (per category + abnormal) and the auto-intensity bounds.
 */
final class Version20260622091609 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add settings table (configurable significance thresholds and auto-intensity bounds)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE settings (id INT AUTO_INCREMENT NOT NULL, consumption_threshold INT NOT NULL, emission_threshold INT NOT NULL, waste_threshold INT NOT NULL, discharge_threshold INT NOT NULL, abnormal_threshold INT NOT NULL, intensity_rise_threshold DOUBLE PRECISION NOT NULL, intensity_drop_threshold DOUBLE PRECISION NOT NULL, intensity_baseline_years INT NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE settings');
    }
}
