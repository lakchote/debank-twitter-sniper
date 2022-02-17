<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20220213165358 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE wallet (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, address VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, nfts CLOB DEFAULT NULL --(DC2Type:json)
        , nodes CLOB DEFAULT NULL --(DC2Type:json)
        , to_snipe BOOLEAN NOT NULL, auto_buy BOOLEAN NOT NULL)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE wallet');
    }
}
