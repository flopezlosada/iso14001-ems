<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds document.last_completed_on: the date an obligation was last closed for a review cycle. The act
 * of completing rolls the scheduled_alert due dates forward; this column is the durable trace of when
 * that happened, shown in the cockpit and kept for the audit.
 */
final class Version20260622140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add document.last_completed_on for the periodic obligation close';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE document ADD last_completed_on DATE DEFAULT NULL COMMENT \'(DC2Type:date_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE document DROP last_completed_on');
    }
}
