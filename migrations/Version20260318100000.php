<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260318100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add roles to user, unique username, fix reservation FK nullable';
    }

    public function up(Schema $schema): void
    {
        // Add roles column to user
        $this->addSql("ALTER TABLE `user` ADD roles JSON NOT NULL COMMENT '(DC2Type:json)'");
        // Make username unique
        $this->addSql('ALTER TABLE `user` CHANGE username username VARCHAR(180) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649F85E0677 ON `user` (username)');

        // Fix reservation event_id to be NOT NULL (drop FK, alter, re-add)
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C8495571F7E88B');
        $this->addSql('ALTER TABLE reservation CHANGE event_id event_id INT NOT NULL');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C8495571F7E88B FOREIGN KEY (event_id) REFERENCES event (id)');

        // Fix phone column length
        $this->addSql('ALTER TABLE reservation CHANGE phone phone VARCHAR(20) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` DROP roles');
        $this->addSql('DROP INDEX UNIQ_8D93D649F85E0677 ON `user`');
        $this->addSql('ALTER TABLE `user` CHANGE username username VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C8495571F7E88B');
        $this->addSql('ALTER TABLE reservation CHANGE event_id event_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C8495571F7E88B FOREIGN KEY (event_id) REFERENCES event (id)');
        $this->addSql('ALTER TABLE reservation CHANGE phone phone VARCHAR(255) NOT NULL');
    }
}
