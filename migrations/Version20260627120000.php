<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds the sealed-PDF and level-1a signature columns to the management review (RG-09.03.01): the
 * official PDF is sealed at approval (storage_path), its bytes hashed (integrity_hash), and an
 * optional PDF signed by Direction may be attached afterwards (signed_pdf_path).
 */
final class Version20260627120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add sealed PDF and level-1a signature columns to management_review';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE management_review ADD storage_path VARCHAR(1024) DEFAULT NULL, ADD integrity_hash VARCHAR(128) DEFAULT NULL, ADD signed_pdf_path VARCHAR(1024) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE management_review DROP storage_path, DROP integrity_hash, DROP signed_pdf_path');
    }
}
