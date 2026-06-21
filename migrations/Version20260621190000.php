<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create the supplier and supplier_evaluation tables (control of suppliers, F.12.0 / PC.05).
 */
final class Version20260621190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the supplier and supplier_evaluation tables.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE supplier (
                id INT AUTO_INCREMENT NOT NULL,
                name VARCHAR(255) NOT NULL,
                product_or_service VARCHAR(255) NOT NULL,
                notes LONGTEXT DEFAULT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE supplier_evaluation (
                id INT AUTO_INCREMENT NOT NULL,
                supplier_id INT NOT NULL,
                evaluation_year INT NOT NULL,
                criterion VARCHAR(20) NOT NULL,
                notes LONGTEXT DEFAULT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE INDEX uniq_supplier_eval_year (supplier_id, evaluation_year),
                INDEX idx_supplier_eval_supplier (supplier_id),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql('ALTER TABLE supplier_evaluation ADD CONSTRAINT fk_supplier_eval_supplier FOREIGN KEY (supplier_id) REFERENCES supplier (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE supplier_evaluation DROP FOREIGN KEY fk_supplier_eval_supplier');
        $this->addSql('DROP TABLE supplier_evaluation');
        $this->addSql('DROP TABLE supplier');
    }
}
