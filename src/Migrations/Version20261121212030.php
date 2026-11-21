<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261121212030 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the nullable dataset_slug key to the importable tables.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE announcement ADD dataset_slug VARCHAR(64) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_4DB9D91C1D19CE67 ON announcement (dataset_slug)');
        $this->addSql('ALTER TABLE map ADD dataset_slug VARCHAR(64) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_93ADAABB1D19CE67 ON map (dataset_slug)');
        $this->addSql('ALTER TABLE map_point ADD dataset_slug VARCHAR(64) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_3753BC481D19CE67 ON map_point (dataset_slug)');
        $this->addSql('ALTER TABLE map_route ADD dataset_slug VARCHAR(64) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_82326F151D19CE67 ON map_route (dataset_slug)');
        $this->addSql('ALTER TABLE map_route_type ADD dataset_slug VARCHAR(64) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_34E413A21D19CE67 ON map_route_type (dataset_slug)');
        $this->addSql('ALTER TABLE "user" ADD dataset_slug VARCHAR(64) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D6491D19CE67 ON "user" (dataset_slug)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_4DB9D91C1D19CE67');
        $this->addSql('ALTER TABLE announcement DROP dataset_slug');
        $this->addSql('DROP INDEX UNIQ_93ADAABB1D19CE67');
        $this->addSql('ALTER TABLE map DROP dataset_slug');
        $this->addSql('DROP INDEX UNIQ_3753BC481D19CE67');
        $this->addSql('ALTER TABLE map_point DROP dataset_slug');
        $this->addSql('DROP INDEX UNIQ_82326F151D19CE67');
        $this->addSql('ALTER TABLE map_route DROP dataset_slug');
        $this->addSql('DROP INDEX UNIQ_34E413A21D19CE67');
        $this->addSql('ALTER TABLE map_route_type DROP dataset_slug');
        $this->addSql('DROP INDEX UNIQ_8D93D6491D19CE67');
        $this->addSql('ALTER TABLE "user" DROP dataset_slug');
    }
}
