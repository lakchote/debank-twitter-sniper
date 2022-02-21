<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20220221093941 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE wallet ADD COLUMN buys CLOB DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TEMPORARY TABLE __temp__wallet AS SELECT id, address, name, nfts, nodes, to_snipe, auto_buy FROM wallet');
        $this->addSql('DROP TABLE wallet');
        $this->addSql('CREATE TABLE wallet (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, address VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, nfts CLOB DEFAULT NULL --(DC2Type:json)
        , nodes CLOB DEFAULT NULL --(DC2Type:json)
        , to_snipe BOOLEAN NOT NULL, auto_buy BOOLEAN NOT NULL)');
        $this->addSql('INSERT INTO wallet (id, address, name, nfts, nodes, to_snipe, auto_buy) SELECT id, address, name, nfts, nodes, to_snipe, auto_buy FROM __temp__wallet');
        $this->addSql('DROP TABLE __temp__wallet');
    }
}
