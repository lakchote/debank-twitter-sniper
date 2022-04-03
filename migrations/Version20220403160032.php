<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20220403160032 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE `transaction` (id INT AUTO_INCREMENT NOT NULL, wallet_id INT DEFAULT NULL, type VARCHAR(255) NOT NULL, date DATETIME NOT NULL, token VARCHAR(255) NOT NULL, tx_url VARCHAR(255) NOT NULL, wallet_net_worth VARCHAR(255) DEFAULT NULL, INDEX IDX_723705D1712520F3 (wallet_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE twitter_influencer (id INT AUTO_INCREMENT NOT NULL, username VARCHAR(255) NOT NULL, following LONGTEXT DEFAULT NULL COMMENT \'(DC2Type:json)\', user_id VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE wallet (id INT AUTO_INCREMENT NOT NULL, address VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, nfts LONGTEXT DEFAULT NULL COMMENT \'(DC2Type:json)\', nodes LONGTEXT DEFAULT NULL COMMENT \'(DC2Type:json)\', buys LONGTEXT DEFAULT NULL COMMENT \'(DC2Type:json)\', stakes LONGTEXT DEFAULT NULL COMMENT \'(DC2Type:json)\', unstakes LONGTEXT DEFAULT NULL COMMENT \'(DC2Type:json)\', swaps LONGTEXT DEFAULT NULL COMMENT \'(DC2Type:json)\', contracts LONGTEXT DEFAULT NULL COMMENT \'(DC2Type:json)\', to_snipe TINYINT(1) NOT NULL, auto_buy TINYINT(1) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE `transaction` ADD CONSTRAINT FK_723705D1712520F3 FOREIGN KEY (wallet_id) REFERENCES wallet (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `transaction` DROP FOREIGN KEY FK_723705D1712520F3');
        $this->addSql('DROP TABLE `transaction`');
        $this->addSql('DROP TABLE twitter_influencer');
        $this->addSql('DROP TABLE wallet');
    }
}
