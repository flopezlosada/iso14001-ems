<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Links an environmental aspect to the utility whose readings feed its auto-intensity
 * (PG-06.01): adds environmental_aspect.linked_consumption_type (nullable, backed enum).
 */
final class Version20260622071058 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add environmental_aspect.linked_consumption_type for consumption auto-intensity';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE environmental_aspect ADD linked_consumption_type VARCHAR(20) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE environmental_aspect DROP linked_consumption_type');
    }
}
