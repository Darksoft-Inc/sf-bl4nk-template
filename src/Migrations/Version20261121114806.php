<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261121114806 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE announcement ADD author_id INT NOT NULL');
        $this->addSql('ALTER TABLE announcement ADD CONSTRAINT FK_4DB9D91CF675F31B FOREIGN KEY (author_id) REFERENCES "user" (id) NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_4DB9D91CF675F31B ON announcement (author_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE announcement DROP CONSTRAINT FK_4DB9D91CF675F31B');
        $this->addSql('DROP INDEX IDX_4DB9D91CF675F31B');
        $this->addSql('ALTER TABLE announcement DROP author_id');
    }
}
