<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260803121840 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE player ALTER easy_mode DROP DEFAULT');
        // Existing rows need a default (table isn't empty), matching the entity default (false).
        $this->addSql('ALTER TABLE theme ADD hardcore BOOLEAN NOT NULL DEFAULT false');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE player ALTER easy_mode SET DEFAULT false');
        $this->addSql('ALTER TABLE theme DROP hardcore');
    }
}
