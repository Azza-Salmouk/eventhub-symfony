<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260326000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fix column type drift: event.image, user.roles, messenger_messages.delivered_at';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE event CHANGE image image VARCHAR(255) DEFAULT NULL");
        $this->addSql("ALTER TABLE `user` CHANGE roles roles JSON NOT NULL");
        $this->addSql("ALTER TABLE messenger_messages CHANGE delivered_at delivered_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE event CHANGE image image VARCHAR(255) DEFAULT NULL");
        $this->addSql("ALTER TABLE `user` CHANGE roles roles JSON NOT NULL");
        $this->addSql("ALTER TABLE messenger_messages CHANGE delivered_at delivered_at DATETIME DEFAULT NULL");
    }
}
