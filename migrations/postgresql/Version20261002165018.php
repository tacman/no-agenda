<?php

declare(strict_types=1);

namespace DoctrineMigrations\Postgresql;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261002165018 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Promote transcript segments to independent workflow subjects, preserving summaries.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE na_episode_segment (dense_summary TEXT DEFAULT NULL, workflow_locked BOOLEAN DEFAULT false NOT NULL, run_subject_id VARCHAR(64) DEFAULT NULL, id VARCHAR(24) NOT NULL, position INT NOT NULL, source JSON NOT NULL, marking VARCHAR(32) DEFAULT NULL, pending_steps JSON DEFAULT \'{}\' NOT NULL, episode_id INT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_6E562476362B62A0 ON na_episode_segment (episode_id)');
        $this->addSql('CREATE INDEX IDX_6E562476362B62A0462CE4F5 ON na_episode_segment (episode_id, position)');
        $this->addSql('ALTER TABLE na_episode_segment ADD CONSTRAINT FK_6E562476362B62A0 FOREIGN KEY (episode_id) REFERENCES na_episode (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql(<<<'SQL'
INSERT INTO na_episode_segment (id, episode_id, position, source, dense_summary, run_subject_id, marking, pending_steps)
SELECT COALESCE(item->>'id', substr(md5(a.episode_id::text || ':' || ordinal::text), 1, 24)), a.episode_id,
       ordinal - 1, item - 'id' - 'summary' - 'run_subject_id', item->>'summary', item->>'run_subject_id',
       CASE WHEN NULLIF(item->>'summary', '') IS NULL THEN 'ai_ready' ELSE 'complete' END,
       CASE WHEN NULLIF(item->>'summary', '') IS NULL THEN '{"ai_task":["episode_summary"]}'::json ELSE '{}'::json END
FROM na_episode_analysis a, jsonb_array_elements(a.segments::jsonb) WITH ORDINALITY AS s(item, ordinal)
SQL);
        $this->addSql('DROP TABLE na_episode_analysis');
        $this->addSql(<<<'SQL'
UPDATE na_episode SET pending_steps = '{"ai_task":["episode_summary"]}' WHERE marking = 'ai_ready'
SQL);

    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Segment workflow results must be retained.');
    }
}
