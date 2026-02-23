<?php

declare(strict_types = 1)
;

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260220150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add ActivityLog entity and deletedAt field to Document, add documents relation to Category';
    }

    public function up(Schema $schema): void
    {
        // Add deletedAt column to document table
        $this->addSql('ALTER TABLE document ADD deleted_at DATETIME DEFAULT NULL');

        // Create activity_log table
        $this->addSql('CREATE TABLE activity_log (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, document_id INT DEFAULT NULL, client_id INT DEFAULT NULL, action VARCHAR(50) NOT NULL, details LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, INDEX idx_activity_created_at (created_at), INDEX idx_activity_action (action), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE activity_log ADD CONSTRAINT FK_activity_log_user FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE activity_log ADD CONSTRAINT FK_activity_log_document FOREIGN KEY (document_id) REFERENCES document (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE activity_log ADD CONSTRAINT FK_activity_log_client FOREIGN KEY (client_id) REFERENCES client (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE activity_log');
        $this->addSql('ALTER TABLE document DROP deleted_at');
    }
}
