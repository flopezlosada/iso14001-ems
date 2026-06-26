<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create the interested_party table (the annual interested-parties register, form F.04.0 / PPI).
 */
final class Version20260626120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the interested_party table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE interested_party (
                id INT AUTO_INCREMENT NOT NULL,
                review_year INT NOT NULL,
                name VARCHAR(255) NOT NULL,
                needs_and_expectations LONGTEXT NOT NULL,
                incidents LONGTEXT DEFAULT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                INDEX idx_interested_party_review_year (review_year),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE interested_party');
    }
}
