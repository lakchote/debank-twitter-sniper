<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20220308141427 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE "transaction" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, wallet_id INTEGER DEFAULT NULL, type VARCHAR(255) NOT NULL, date DATETIME NOT NULL, token VARCHAR(255) NOT NULL)');
        $this->addSql('CREATE INDEX IDX_723705D1712520F3 ON "transaction" (wallet_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__wallet AS SELECT id, address, name, nfts, nodes, buys, to_snipe, auto_buy FROM wallet');
        $this->addSql('DROP TABLE wallet');
        $this->addSql('CREATE TABLE wallet (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, address VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, nfts CLOB DEFAULT NULL --(DC2Type:json)
        , nodes CLOB DEFAULT NULL --(DC2Type:json)
        , buys CLOB DEFAULT NULL --(DC2Type:json)
        , to_snipe BOOLEAN NOT NULL, auto_buy BOOLEAN NOT NULL, stakes CLOB DEFAULT NULL --(DC2Type:json)
        , unstakes CLOB DEFAULT NULL --(DC2Type:json)
        )');
        $this->addSql('INSERT INTO wallet (id, address, name, nfts, nodes, buys, to_snipe, auto_buy) SELECT id, address, name, nfts, nodes, buys, to_snipe, auto_buy FROM __temp__wallet');
        $this->addSql('DROP TABLE __temp__wallet');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE "transaction"');
        $this->addSql('CREATE TEMPORARY TABLE __temp__wallet AS SELECT id, address, name, nfts, nodes, buys, to_snipe, auto_buy FROM wallet');
        $this->addSql('DROP TABLE wallet');
        $this->addSql('CREATE TABLE wallet (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, address VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, nfts CLOB DEFAULT NULL --(DC2Type:json)
        , nodes CLOB DEFAULT NULL --(DC2Type:json)
        , buys CLOB DEFAULT NULL, to_snipe BOOLEAN NOT NULL, auto_buy BOOLEAN NOT NULL)');
        $this->addSql('INSERT INTO wallet (id, address, name, nfts, nodes, buys, to_snipe, auto_buy) SELECT id, address, name, nfts, nodes, buys, to_snipe, auto_buy FROM __temp__wallet');
        $this->addSql('DROP TABLE __temp__wallet');
    }
}