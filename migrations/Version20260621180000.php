<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add the invoice attachment columns to consumption_reading.
 */
final class Version20260621180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add invoice attachment (path + original name) to consumption_reading.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE consumption_reading ADD invoice_path VARCHAR(255) DEFAULT NULL, ADD invoice_original_name VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE consumption_reading DROP invoice_path, DROP invoice_original_name');
    }
}
