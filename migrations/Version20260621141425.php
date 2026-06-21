<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260621141425 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create waste_record table for the chronological waste register.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE waste_record (id INT AUTO_INCREMENT NOT NULL, ler_code VARCHAR(20) NOT NULL, description VARCHAR(255) NOT NULL, quantity_kg NUMERIC(12, 3) NOT NULL, pickup_date DATE NOT NULL, manager VARCHAR(255) NOT NULL, hazardous TINYINT NOT NULL, notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX idx_waste_pickup_date (pickup_date), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE waste_record');
    }
}
