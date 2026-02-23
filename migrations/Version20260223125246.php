<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260223125246 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE activity_log DROP FOREIGN KEY `FK_activity_log_client`');
        $this->addSql('ALTER TABLE activity_log DROP FOREIGN KEY `FK_activity_log_document`');
        $this->addSql('ALTER TABLE activity_log ADD CONSTRAINT FK_FD06F647C33F7837 FOREIGN KEY (document_id) REFERENCES document (id)');
        $this->addSql('ALTER TABLE activity_log ADD CONSTRAINT FK_FD06F64719EB6921 FOREIGN KEY (client_id) REFERENCES client (id)');
        $this->addSql('ALTER TABLE activity_log RENAME INDEX fk_activity_log_user TO IDX_FD06F647A76ED395');
        $this->addSql('ALTER TABLE activity_log RENAME INDEX fk_activity_log_document TO IDX_FD06F647C33F7837');
        $this->addSql('ALTER TABLE activity_log RENAME INDEX fk_activity_log_client TO IDX_FD06F64719EB6921');
        $this->addSql('ALTER TABLE user ADD username VARCHAR(180) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649F85E0677 ON user (username)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE activity_log DROP FOREIGN KEY FK_FD06F647C33F7837');
        $this->addSql('ALTER TABLE activity_log DROP FOREIGN KEY FK_FD06F64719EB6921');
        $this->addSql('ALTER TABLE activity_log ADD CONSTRAINT `FK_activity_log_client` FOREIGN KEY (client_id) REFERENCES client (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE activity_log ADD CONSTRAINT `FK_activity_log_document` FOREIGN KEY (document_id) REFERENCES document (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE activity_log RENAME INDEX idx_fd06f64719eb6921 TO FK_activity_log_client');
        $this->addSql('ALTER TABLE activity_log RENAME INDEX idx_fd06f647a76ed395 TO FK_activity_log_user');
        $this->addSql('ALTER TABLE activity_log RENAME INDEX idx_fd06f647c33f7837 TO FK_activity_log_document');
        $this->addSql('DROP INDEX UNIQ_8D93D649F85E0677 ON user');
        $this->addSql('ALTER TABLE user DROP username');
    }
}
