<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20220412060513 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE wallet ADD label VARCHAR(255) NOT NULL, DROP nodes');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE wallet ADD nodes LONGTEXT DEFAULT NULL COMMENT \'(DC2Type:json)\', DROP label');
    }
}
