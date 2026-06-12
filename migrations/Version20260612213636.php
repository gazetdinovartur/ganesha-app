<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260612213636 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add dish categories and link dishes to category';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE dish_category (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(120) NOT NULL, sort_order INT NOT NULL, UNIQUE INDEX uniq_dish_category_name (name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE dish ADD category_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE dish ADD CONSTRAINT FK_957D8CB812469DE2 FOREIGN KEY (category_id) REFERENCES dish_category (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_957D8CB812469DE2 ON dish (category_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE dish DROP FOREIGN KEY FK_957D8CB812469DE2');
        $this->addSql('DROP INDEX IDX_957D8CB812469DE2 ON dish');
        $this->addSql('ALTER TABLE dish DROP category_id');
        $this->addSql('DROP TABLE dish_category');
    }
}
