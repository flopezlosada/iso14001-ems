<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds the automatic non-conformity feature (PC.10.0): the per-rule toggles on the settings
 * (breached indicators, unmet objectives) and the unique source key on a non-conformity that makes
 * the auto-generation idempotent.
 */
final class Version20260627150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add auto-non-conformity toggles to settings and non_conformity.auto_source_key';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE settings ADD auto_nc_from_breached_indicators TINYINT NOT NULL DEFAULT 0, ADD auto_nc_from_unmet_objectives TINYINT NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE non_conformity ADD auto_source_key VARCHAR(191) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_nc_auto_source_key ON non_conformity (auto_source_key)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_nc_auto_source_key ON non_conformity');
        $this->addSql('ALTER TABLE non_conformity DROP auto_source_key');
        $this->addSql('ALTER TABLE settings DROP auto_nc_from_breached_indicators, DROP auto_nc_from_unmet_objectives');
    }
}
