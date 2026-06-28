<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds the `decision` column to management_review_section: the closed verdict an output (decision)
 * section of the management review carries (ISO 14001 §9.3.3), alongside its free-text detail.
 */
final class Version20260628160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'management_review_section: add decision (closed verdict for §9.3.3 output sections)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE management_review_section ADD decision VARCHAR(80) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE management_review_section DROP decision');
    }
}
