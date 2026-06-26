<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Creates the management review (RG-09.03.01, ISO 14001:2015 §9.3): one review per course, its
 * ordered sections (with editable content and the frozen auto-fill snapshot) and the join table of
 * participating users.
 */
final class Version20260626204031 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create management_review, management_review_section and management_review_participant';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE management_review (id INT AUTO_INCREMENT NOT NULL, exercise VARCHAR(9) NOT NULL, meeting_date DATE DEFAULT NULL, approved_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, approved_by_id INT DEFAULT NULL, INDEX IDX_4F5A850C2D234F6A (approved_by_id), UNIQUE INDEX uniq_management_review_exercise (exercise), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE management_review_participant (management_review_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_2583D7E4A211E8FF (management_review_id), INDEX IDX_2583D7E4A76ED395 (user_id), PRIMARY KEY (management_review_id, user_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE management_review_section (id INT AUTO_INCREMENT NOT NULL, section_key VARCHAR(40) NOT NULL, position SMALLINT NOT NULL, content LONGTEXT DEFAULT NULL, generated_snapshot LONGTEXT DEFAULT NULL, review_id INT NOT NULL, INDEX IDX_A30CAF813E2E969B (review_id), UNIQUE INDEX uniq_review_section_key (review_id, section_key), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE management_review ADD CONSTRAINT FK_4F5A850C2D234F6A FOREIGN KEY (approved_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE management_review_participant ADD CONSTRAINT FK_2583D7E4A211E8FF FOREIGN KEY (management_review_id) REFERENCES management_review (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE management_review_participant ADD CONSTRAINT FK_2583D7E4A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE management_review_section ADD CONSTRAINT FK_A30CAF813E2E969B FOREIGN KEY (review_id) REFERENCES management_review (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE management_review DROP FOREIGN KEY FK_4F5A850C2D234F6A');
        $this->addSql('ALTER TABLE management_review_participant DROP FOREIGN KEY FK_2583D7E4A211E8FF');
        $this->addSql('ALTER TABLE management_review_participant DROP FOREIGN KEY FK_2583D7E4A76ED395');
        $this->addSql('ALTER TABLE management_review_section DROP FOREIGN KEY FK_A30CAF813E2E969B');
        $this->addSql('DROP TABLE management_review_section');
        $this->addSql('DROP TABLE management_review_participant');
        $this->addSql('DROP TABLE management_review');
    }
}
