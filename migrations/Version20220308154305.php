<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20220308154305 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_723705D1712520F3');
        $this->addSql('CREATE TEMPORARY TABLE __temp__transaction AS SELECT id, wallet_id, type, date, token FROM "transaction"');
        $this->addSql('DROP TABLE "transaction"');
        $this->addSql('CREATE TABLE "transaction" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, wallet_id INTEGER DEFAULT NULL, type VARCHAR(255) NOT NULL, date DATETIME NOT NULL, token VARCHAR(255) NOT NULL, tx_url VARCHAR(255) NOT NULL, CONSTRAINT FK_723705D1712520F3 FOREIGN KEY (wallet_id) REFERENCES wallet (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO "transaction" (id, wallet_id, type, date, token) SELECT id, wallet_id, type, date, token FROM __temp__transaction');
        $this->addSql('DROP TABLE __temp__transaction');
        $this->addSql('CREATE INDEX IDX_723705D1712520F3 ON "transaction" (wallet_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_723705D1712520F3');
        $this->addSql('CREATE TEMPORARY TABLE __temp__transaction AS SELECT id, wallet_id, type, date, token FROM "transaction"');
        $this->addSql('DROP TABLE "transaction"');
        $this->addSql('CREATE TABLE "transaction" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, wallet_id INTEGER DEFAULT NULL, type VARCHAR(255) NOT NULL, date DATETIME NOT NULL, token VARCHAR(255) NOT NULL)');
        $this->addSql('INSERT INTO "transaction" (id, wallet_id, type, date, token) SELECT id, wallet_id, type, date, token FROM __temp__transaction');
        $this->addSql('DROP TABLE __temp__transaction');
        $this->addSql('CREATE INDEX IDX_723705D1712520F3 ON "transaction" (wallet_id)');
    }
}
