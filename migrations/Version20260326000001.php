<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260326000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add refresh_tokens table (Gesdinet) and WebauthnCredential table';
    }

    public function up(Schema $schema): void
    {
        // Gesdinet refresh tokens
        $this->addSql('CREATE TABLE refresh_tokens (
            id INT AUTO_INCREMENT NOT NULL,
            refresh_token VARCHAR(128) NOT NULL,
            username VARCHAR(255) NOT NULL,
            valid DATETIME NOT NULL,
            UNIQUE INDEX UNIQ_9BACE7E1C74F2195 (refresh_token),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');

        // WebAuthn credentials
        $this->addSql('CREATE TABLE webauthn_credential (
            id VARCHAR(255) NOT NULL,
            user_id INT NOT NULL,
            public_key LONGTEXT NOT NULL,
            counter INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            INDEX IDX_WEBAUTHN_USER (user_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');

        $this->addSql('ALTER TABLE webauthn_credential
            ADD CONSTRAINT FK_WEBAUTHN_USER
            FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE webauthn_credential DROP FOREIGN KEY FK_WEBAUTHN_USER');
        $this->addSql('DROP TABLE webauthn_credential');
        $this->addSql('DROP TABLE refresh_tokens');
    }
}
