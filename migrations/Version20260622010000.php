<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add the explicit admin flag to role. Admin power stops being a side effect of the role code
 * ("admin") and becomes an auditable column; the existing admin role keeps its power via a backfill.
 */
final class Version20260622000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add role.admin (explicit superuser flag) and backfill the legacy admin role.';
    }

    public function up(Schema $schema): void
    {
        // NOT NULL without a DB default: MySQL backfills existing rows with 0 on ALTER, and the
        // entity declares no DB default either, so doctrine:schema:validate stays clean.
        $this->addSql('ALTER TABLE role ADD admin TINYINT(1) NOT NULL');
        // Preserve current behaviour: the role that used to get ROLE_ADMIN via its code stays admin.
        $this->addSql("UPDATE role SET admin = 1 WHERE code = 'admin'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE role DROP admin');
    }
}
