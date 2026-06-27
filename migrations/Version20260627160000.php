<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Reconciles the schema with the current entity mappings after the parallel feature merges.
 *
 * Purely non-destructive housekeeping (no data loss): renames the hand-named indexes to Doctrine's
 * convention-generated names, drops two indexes the entities no longer declare, and normalises a
 * couple of column declarations (the date_immutable comment and the auto-NC flags' DB default).
 * Brings `doctrine:schema:validate` back in sync.
 */
final class Version20260627160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Reconcile index names and column declarations with the entity mappings';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE aspect_evaluation RENAME INDEX idx_aspect_eval_aspect TO IDX_4ABAB90998507F8C');
        $this->addSql('ALTER TABLE corrective_action RENAME INDEX idx_ca_responsible TO IDX_ECD872CE602AD315');
        $this->addSql('ALTER TABLE corrective_action RENAME INDEX idx_ca_authorized_by TO IDX_ECD872CE2B62D3A1');
        $this->addSql('ALTER TABLE corrective_action RENAME INDEX idx_ca_reviewed_by TO IDX_ECD872CEFC6B21F1');
        $this->addSql('ALTER TABLE dafo_analysis RENAME INDEX uniq_dafo_analysis_school_year TO UNIQ_22BE4355FAAAACDA');
        $this->addSql('ALTER TABLE document CHANGE last_completed_on last_completed_on DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE indicator_measurement RENAME INDEX idx_indicator_measurement_indicator TO IDX_1CA2A904402854A');
        $this->addSql('DROP INDEX idx_interested_party_review_year ON interested_party');
        $this->addSql('ALTER TABLE legal_requirement RENAME INDEX uniq_legal_requirement_reference TO UNIQ_CEF9F8B1AEA34913');
        $this->addSql('ALTER TABLE non_conformity RENAME INDEX uniq_nc_reference TO UNIQ_9726A49AAEA34913');
        $this->addSql('ALTER TABLE non_conformity RENAME INDEX idx_nc_responsible TO IDX_9726A49A602AD315');
        $this->addSql('ALTER TABLE objective RENAME INDEX uniq_objective_reference TO UNIQ_B996F101AEA34913');
        $this->addSql('ALTER TABLE objective RENAME INDEX idx_objective_responsible TO IDX_B996F101602AD315');
        $this->addSql('ALTER TABLE objective RENAME INDEX idx_objective_aspect TO IDX_B996F10130912397');
        $this->addSql('ALTER TABLE settings CHANGE auto_nc_from_breached_indicators auto_nc_from_breached_indicators TINYINT NOT NULL, CHANGE auto_nc_from_unmet_objectives auto_nc_from_unmet_objectives TINYINT NOT NULL');
        $this->addSql('ALTER TABLE supplier_evaluation RENAME INDEX idx_supplier_eval_supplier TO IDX_72D2689C2ADD6D8C');
        $this->addSql('ALTER TABLE supplier_incident RENAME INDEX idx_supplier_incident_supplier TO IDX_B08AC99B2ADD6D8C');
        $this->addSql('DROP INDEX idx_training_plan_year ON training_action');
        $this->addSql('ALTER TABLE waste_record CHANGE pickup_date pickup_date DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE waste_record RENAME INDEX uniq_waste_source_hash TO UNIQ_6D0C421DD032E7BB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE aspect_evaluation RENAME INDEX idx_4abab90998507f8c TO idx_aspect_eval_aspect');
        $this->addSql('ALTER TABLE corrective_action RENAME INDEX idx_ecd872ce602ad315 TO idx_ca_responsible');
        $this->addSql('ALTER TABLE corrective_action RENAME INDEX idx_ecd872ce2b62d3a1 TO idx_ca_authorized_by');
        $this->addSql('ALTER TABLE corrective_action RENAME INDEX idx_ecd872cefc6b21f1 TO idx_ca_reviewed_by');
        $this->addSql('ALTER TABLE dafo_analysis RENAME INDEX uniq_22be4355faaaacda TO uniq_dafo_analysis_school_year');
        $this->addSql("ALTER TABLE document CHANGE last_completed_on last_completed_on DATE DEFAULT NULL COMMENT '(DC2Type:date_immutable)'");
        $this->addSql('ALTER TABLE indicator_measurement RENAME INDEX idx_1ca2a904402854a TO idx_indicator_measurement_indicator');
        $this->addSql('CREATE INDEX idx_interested_party_review_year ON interested_party (review_year)');
        $this->addSql('ALTER TABLE legal_requirement RENAME INDEX uniq_cef9f8b1aea34913 TO uniq_legal_requirement_reference');
        $this->addSql('ALTER TABLE non_conformity RENAME INDEX uniq_9726a49aaea34913 TO uniq_nc_reference');
        $this->addSql('ALTER TABLE non_conformity RENAME INDEX idx_9726a49a602ad315 TO idx_nc_responsible');
        $this->addSql('ALTER TABLE objective RENAME INDEX uniq_b996f101aea34913 TO uniq_objective_reference');
        $this->addSql('ALTER TABLE objective RENAME INDEX idx_b996f101602ad315 TO idx_objective_responsible');
        $this->addSql('ALTER TABLE objective RENAME INDEX idx_b996f10130912397 TO idx_objective_aspect');
        $this->addSql('ALTER TABLE settings CHANGE auto_nc_from_breached_indicators auto_nc_from_breached_indicators TINYINT DEFAULT 0 NOT NULL, CHANGE auto_nc_from_unmet_objectives auto_nc_from_unmet_objectives TINYINT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE supplier_evaluation RENAME INDEX idx_72d2689c2add6d8c TO idx_supplier_eval_supplier');
        $this->addSql('ALTER TABLE supplier_incident RENAME INDEX idx_b08ac99b2add6d8c TO idx_supplier_incident_supplier');
        $this->addSql('CREATE INDEX idx_training_plan_year ON training_action (plan_year)');
        $this->addSql("ALTER TABLE waste_record CHANGE pickup_date pickup_date DATE DEFAULT NULL COMMENT '(DC2Type:date_immutable)'");
        $this->addSql('ALTER TABLE waste_record RENAME INDEX uniq_6d0c421dd032e7bb TO uniq_waste_source_hash');
    }
}
