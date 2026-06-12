<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260613120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Согласие на обработку ПДн у клиента и сессии ботов TG/VK.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE customer ADD personal_data_consent_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');

        $this->addSql('CREATE TABLE bot_session (
            id INT AUTO_INCREMENT NOT NULL,
            platform VARCHAR(16) NOT NULL,
            external_user_id VARCHAR(64) NOT NULL,
            state VARCHAR(64) NOT NULL,
            payload JSON NOT NULL,
            updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            UNIQUE INDEX uniq_bot_session_user (platform, external_user_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE bot_session');
        $this->addSql('ALTER TABLE customer DROP personal_data_consent_at');
    }
}
