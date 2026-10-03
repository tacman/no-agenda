<?php

declare(strict_types=1);

namespace DoctrineMigrations\Postgresql;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261002151418 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add episode workflow state, bounded AI segments and claim provenance';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE claim (id VARCHAR(26) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, scope VARCHAR(64) DEFAULT NULL, subject_type VARCHAR(32) NOT NULL, subject_id VARCHAR(64) NOT NULL, predicate VARCHAR(64) NOT NULL, source VARCHAR(128) NOT NULL, value JSONB NOT NULL, confidence SMALLINT NOT NULL, basis TEXT DEFAULT NULL, run_id VARCHAR(26) DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_claim_scope_subject_pred ON claim (scope, subject_type, subject_id, predicate)');
        $this->addSql('CREATE INDEX idx_claim_scope_source ON claim (scope, source)');
        $this->addSql('CREATE INDEX idx_claim_run ON claim (run_id)');
        $this->addSql('CREATE TABLE claim_run (id VARCHAR(26) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, scope VARCHAR(64) DEFAULT NULL, subject_type VARCHAR(32) NOT NULL, subject_id VARCHAR(64) NOT NULL, source VARCHAR(128) NOT NULL, model VARCHAR(128) DEFAULT NULL, prompt TEXT DEFAULT NULL, response JSONB DEFAULT NULL, input_tokens INT DEFAULT NULL, output_tokens INT DEFAULT NULL, image_tokens INT DEFAULT NULL, duration_ms INT DEFAULT NULL, claim_count INT DEFAULT 0 NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_claim_run_scope_subject_source ON claim_run (scope, subject_type, subject_id, source)');
        $this->addSql('CREATE INDEX idx_claim_run_created ON claim_run (created_at)');
        $this->addSql('ALTER TABLE na_episode ADD workflow_locked BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE na_episode ADD dense_summary TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE na_episode ADD ai_segments JSON DEFAULT \'[]\' NOT NULL');
        $this->addSql('ALTER TABLE na_episode ADD ai_cursor INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE na_episode ADD marking VARCHAR(32) DEFAULT NULL');
        $this->addSql('ALTER TABLE na_episode ADD pending_steps JSON DEFAULT \'{}\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE claim');
        $this->addSql('DROP TABLE claim_run');
        $this->addSql('ALTER TABLE na_episode DROP workflow_locked');
        $this->addSql('ALTER TABLE na_episode DROP dense_summary');
        $this->addSql('ALTER TABLE na_episode DROP ai_segments');
        $this->addSql('ALTER TABLE na_episode DROP ai_cursor');
        $this->addSql('ALTER TABLE na_episode DROP marking');
        $this->addSql('ALTER TABLE na_episode DROP pending_steps');
    }
}
