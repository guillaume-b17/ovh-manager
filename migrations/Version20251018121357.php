<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251018121357 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE email_account ADD user_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE email_account ADD CONSTRAINT FK_C0F63E6BA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_C0F63E6BA76ED395 ON email_account (user_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE email_account DROP FOREIGN KEY FK_C0F63E6BA76ED395');
        $this->addSql('DROP INDEX IDX_C0F63E6BA76ED395 ON email_account');
        $this->addSql('ALTER TABLE email_account DROP user_id');
    }
}
