<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds settings.start_month: the month (1-12) the annual calendar starts on, so the year-at-a-glance
 * can span the centre's audit cycle. Presentation only. Defaults to 1 (January) for existing rows.
 */
final class Version20260628120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'settings: add start_month (mes de inicio del calendario anual, presentación)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE settings ADD start_month INT DEFAULT 1 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE settings DROP start_month');
    }
}
