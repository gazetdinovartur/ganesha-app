<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260614120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Группа оплаты для мультидневного web-заказа.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `order` ADD payment_group_uuid BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('CREATE INDEX idx_order_payment_group ON `order` (payment_group_uuid)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_order_payment_group ON `order`');
        $this->addSql('ALTER TABLE `order` DROP payment_group_uuid');
    }
}
