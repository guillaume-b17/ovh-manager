<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251020155319 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE redirection ADD from_account_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE redirection ADD CONSTRAINT FK_224450F7B0CF99BD FOREIGN KEY (from_account_id) REFERENCES email_account (id)');
        $this->addSql('CREATE INDEX IDX_224450F7B0CF99BD ON redirection (from_account_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE redirection DROP FOREIGN KEY FK_224450F7B0CF99BD');
        $this->addSql('DROP INDEX IDX_224450F7B0CF99BD ON redirection');
        $this->addSql('ALTER TABLE redirection DROP from_account_id');
    }
}
