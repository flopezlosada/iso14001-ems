<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Operational control module (PG-08.01 / RG-08.01.01): the configurable checklist catalogue
 * (operational_control_item), the monthly inspection header (operational_control_check) and one
 * answer per checked item (operational_control_answer).
 */
final class Version20260622083043 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Operational control: checklist catalogue, monthly inspections and answers';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE operational_control_item (id INT AUTO_INCREMENT NOT NULL, section VARCHAR(20) NOT NULL, label VARCHAR(255) NOT NULL, position INT NOT NULL, active TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE operational_control_check (id INT AUTO_INCREMENT NOT NULL, period_year INT NOT NULL, period_month INT NOT NULL, performed_by VARCHAR(255) NOT NULL, observations LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX uniq_opcontrol_period (period_year, period_month), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE operational_control_answer (id INT AUTO_INCREMENT NOT NULL, result VARCHAR(20) DEFAULT NULL, check_id INT NOT NULL, item_id INT NOT NULL, INDEX IDX_2149101C709385E7 (check_id), INDEX IDX_2149101C126F525E (item_id), UNIQUE INDEX uniq_opcontrol_answer (check_id, item_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE operational_control_answer ADD CONSTRAINT FK_2149101C709385E7 FOREIGN KEY (check_id) REFERENCES operational_control_check (id)');
        $this->addSql('ALTER TABLE operational_control_answer ADD CONSTRAINT FK_2149101C126F525E FOREIGN KEY (item_id) REFERENCES operational_control_item (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE operational_control_answer DROP FOREIGN KEY FK_2149101C709385E7');
        $this->addSql('ALTER TABLE operational_control_answer DROP FOREIGN KEY FK_2149101C126F525E');
        $this->addSql('DROP TABLE operational_control_answer');
        $this->addSql('DROP TABLE operational_control_check');
        $this->addSql('DROP TABLE operational_control_item');
    }
}
