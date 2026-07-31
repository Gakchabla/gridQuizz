<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260730133036 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE question RENAME COLUMN is_answered TO answered');
        $this->addSql('ALTER TABLE session RENAME COLUMN is_shuffled TO shuffled');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE question RENAME COLUMN answered TO is_answered');
        $this->addSql('ALTER TABLE session RENAME COLUMN shuffled TO is_shuffled');
    }
}
