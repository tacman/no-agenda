<?php

declare(strict_types=1);

namespace DoctrineMigrations\Postgresql;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261002151551 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the AI workflow bundle subject store';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE subject (id VARCHAR(26) NOT NULL, scope VARCHAR(255) DEFAULT NULL, subject_type VARCHAR(255) NOT NULL, subject_id VARCHAR(255) NOT NULL, data JSON DEFAULT \'{}\' NOT NULL, workflow_locked BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, marking VARCHAR(32) DEFAULT NULL, pending_steps JSON DEFAULT \'{}\' NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_subject ON subject (scope, subject_type, subject_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE subject');
    }
}
