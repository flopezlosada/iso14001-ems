<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260628175527 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the rich-text body to a document revision (documents drafted in-app).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE document_version ADD body LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE document_version DROP body');
    }
}
