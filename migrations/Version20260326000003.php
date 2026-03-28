<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260326000003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fix remaining schema drift: webauthn counter NOT NULL, rename index, column types';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE event CHANGE image image VARCHAR(255) DEFAULT NULL");
        $this->addSql("ALTER TABLE `user` CHANGE roles roles JSON NOT NULL");
        $this->addSql("ALTER TABLE webauthn_credential CHANGE counter counter INT NOT NULL");
        // Drop FK, drop old index, add new index with Doctrine-expected name, re-add FK
        $this->addSql("ALTER TABLE webauthn_credential DROP FOREIGN KEY FK_WEBAUTHN_USER");
        $this->addSql("ALTER TABLE webauthn_credential DROP INDEX idx_webauthn_user");
        $this->addSql("ALTER TABLE webauthn_credential ADD INDEX IDX_850123F9A76ED395 (user_id)");
        $this->addSql("ALTER TABLE webauthn_credential ADD CONSTRAINT FK_850123F9A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE");
        $this->addSql("ALTER TABLE messenger_messages CHANGE delivered_at delivered_at DATETIME DEFAULT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE webauthn_credential DROP FOREIGN KEY FK_850123F9A76ED395");
        $this->addSql("ALTER TABLE webauthn_credential DROP INDEX IDX_850123F9A76ED395");
        $this->addSql("ALTER TABLE webauthn_credential ADD INDEX idx_webauthn_user (user_id)");
        $this->addSql("ALTER TABLE webauthn_credential ADD CONSTRAINT FK_WEBAUTHN_USER FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE");
    }
}
