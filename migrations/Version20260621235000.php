<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add the abnormal (probability/control/severity) and indirect (influence) criteria columns to
 * aspect_evaluation (PG-06.01 Anexos II and III).
 */
final class Version20260621235000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add abnormal and indirect aspect criteria to aspect_evaluation.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE aspect_evaluation ADD probability SMALLINT DEFAULT NULL, ADD control SMALLINT DEFAULT NULL, ADD severity SMALLINT DEFAULT NULL, ADD influence SMALLINT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE aspect_evaluation DROP probability, DROP control, DROP severity, DROP influence');
    }
}
