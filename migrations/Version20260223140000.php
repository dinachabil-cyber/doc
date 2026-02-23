<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260223140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create password_resets table for secure password reset functionality';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE password_resets (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            token_hash VARCHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)",
            used_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime_immutable)",
            created_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)",
            INDEX IDX_password_resets_user_id (user_id),
            INDEX IDX_password_resets_token_hash (token_hash),
            INDEX IDX_password_resets_expires_at (expires_at),
            CONSTRAINT FK_password_resets_user_id FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE password_resets');
    }
}
