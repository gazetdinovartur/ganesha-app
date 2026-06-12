<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260612200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial schema: dishes, menu, orders, customers, pickup points, admin user';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE pickup_point (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(120) NOT NULL, address VARCHAR(255) NOT NULL, pickup_hours VARCHAR(120) NOT NULL, description LONGTEXT DEFAULT NULL, is_active TINYINT(1) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE dish (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(160) NOT NULL, short_description LONGTEXT DEFAULT NULL, composition JSON NOT NULL, price INT NOT NULL, photo_path VARCHAR(255) DEFAULT NULL, is_active TINYINT(1) NOT NULL, sort_order INT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE menu_day (id INT AUTO_INCREMENT NOT NULL, date DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\', is_published TINYINT(1) NOT NULL, note LONGTEXT DEFAULT NULL, UNIQUE INDEX uniq_menu_day_date (date), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE menu_day_dish (id INT AUTO_INCREMENT NOT NULL, menu_day_id INT NOT NULL, dish_id INT NOT NULL, price_override INT DEFAULT NULL, sort_order INT NOT NULL, is_available TINYINT(1) NOT NULL, ordered_portions INT NOT NULL, INDEX IDX_MENU_DAY (menu_day_id), INDEX IDX_DISH (dish_id), UNIQUE INDEX uniq_menu_day_dish (menu_day_id, dish_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE customer (id INT AUTO_INCREMENT NOT NULL, phone VARCHAR(32) NOT NULL, name VARCHAR(120) NOT NULL, telegram_id BIGINT DEFAULT NULL, vk_id BIGINT DEFAULT NULL, default_comment LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_CUSTOMER_PHONE (phone), UNIQUE INDEX UNIQ_CUSTOMER_TELEGRAM (telegram_id), UNIQUE INDEX UNIQ_CUSTOMER_VK (vk_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `order` (id INT AUTO_INCREMENT NOT NULL, customer_id INT NOT NULL, pickup_point_id INT NOT NULL, uuid BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', human_number INT NOT NULL, pickup_date DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\', channel VARCHAR(16) NOT NULL, status VARCHAR(32) NOT NULL, total_amount INT NOT NULL, comment LONGTEXT DEFAULT NULL, repeat_token VARCHAR(64) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', paid_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', payment_claimed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX uniq_order_uuid (uuid), UNIQUE INDEX uniq_order_human_number (human_number), UNIQUE INDEX uniq_order_repeat_token (repeat_token), INDEX IDX_ORDER_CUSTOMER (customer_id), INDEX IDX_ORDER_PICKUP_POINT (pickup_point_id), INDEX IDX_ORDER_PICKUP_DATE (pickup_date), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE order_item (id INT AUTO_INCREMENT NOT NULL, order_id INT NOT NULL, dish_id INT DEFAULT NULL, quantity INT NOT NULL, dish_snapshot JSON NOT NULL, INDEX IDX_ORDER_ITEM_ORDER (order_id), INDEX IDX_ORDER_ITEM_DISH (dish_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE admin_user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_ADMIN_EMAIL (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE menu_day_dish ADD CONSTRAINT FK_MENU_DAY FOREIGN KEY (menu_day_id) REFERENCES menu_day (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE menu_day_dish ADD CONSTRAINT FK_MENU_DISH FOREIGN KEY (dish_id) REFERENCES dish (id)');
        $this->addSql('ALTER TABLE `order` ADD CONSTRAINT FK_ORDER_CUSTOMER FOREIGN KEY (customer_id) REFERENCES customer (id)');
        $this->addSql('ALTER TABLE `order` ADD CONSTRAINT FK_ORDER_PICKUP_POINT FOREIGN KEY (pickup_point_id) REFERENCES pickup_point (id)');
        $this->addSql('ALTER TABLE order_item ADD CONSTRAINT FK_ORDER_ITEM_ORDER FOREIGN KEY (order_id) REFERENCES `order` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE order_item ADD CONSTRAINT FK_ORDER_ITEM_DISH FOREIGN KEY (dish_id) REFERENCES dish (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE menu_day_dish DROP FOREIGN KEY FK_MENU_DAY');
        $this->addSql('ALTER TABLE menu_day_dish DROP FOREIGN KEY FK_MENU_DISH');
        $this->addSql('ALTER TABLE `order` DROP FOREIGN KEY FK_ORDER_CUSTOMER');
        $this->addSql('ALTER TABLE `order` DROP FOREIGN KEY FK_ORDER_PICKUP_POINT');
        $this->addSql('ALTER TABLE order_item DROP FOREIGN KEY FK_ORDER_ITEM_ORDER');
        $this->addSql('ALTER TABLE order_item DROP FOREIGN KEY FK_ORDER_ITEM_DISH');
        $this->addSql('DROP TABLE order_item');
        $this->addSql('DROP TABLE `order`');
        $this->addSql('DROP TABLE customer');
        $this->addSql('DROP TABLE menu_day_dish');
        $this->addSql('DROP TABLE menu_day');
        $this->addSql('DROP TABLE dish');
        $this->addSql('DROP TABLE pickup_point');
        $this->addSql('DROP TABLE admin_user');
    }
}
