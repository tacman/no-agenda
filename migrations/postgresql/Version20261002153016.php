<?php

declare(strict_types=1);

namespace DoctrineMigrations\Postgresql;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261002153016 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Separate transcript analysis from episode catalogue metadata';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE na_episode_analysis (segments JSON NOT NULL, cursor INT NOT NULL, episode_id INT NOT NULL, PRIMARY KEY (episode_id))');
        $this->addSql('ALTER TABLE na_episode_analysis ADD CONSTRAINT FK_84FB513F362B62A0 FOREIGN KEY (episode_id) REFERENCES na_episode (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('INSERT INTO na_episode_analysis (episode_id, segments, cursor) SELECT id, ai_segments, ai_cursor FROM na_episode');
        $this->addSql('ALTER TABLE na_episode DROP ai_segments');
        $this->addSql('ALTER TABLE na_episode DROP ai_cursor');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE na_episode_analysis DROP CONSTRAINT FK_84FB513F362B62A0');
        $this->addSql('DROP TABLE na_episode_analysis');
        $this->addSql('ALTER TABLE na_episode ADD ai_segments JSON DEFAULT \'[]\' NOT NULL');
        $this->addSql('ALTER TABLE na_episode ADD ai_cursor INT DEFAULT 0 NOT NULL');
    }
}
