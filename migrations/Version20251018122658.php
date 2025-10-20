<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251018122658 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE responder (id INT AUTO_INCREMENT NOT NULL, content LONGTEXT DEFAULT NULL, from_date DATETIME DEFAULT NULL, to_date DATETIME DEFAULT NULL, copy TINYINT(1) DEFAULT NULL, copy_to VARCHAR(255) DEFAULT NULL, date_sync DATETIME DEFAULT NULL, email_account_id INT NOT NULL, INDEX IDX_5F311AF737D8AD65 (email_account_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE responder ADD CONSTRAINT FK_5F311AF737D8AD65 FOREIGN KEY (email_account_id) REFERENCES email_account (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE responder DROP FOREIGN KEY FK_5F311AF737D8AD65');
        $this->addSql('DROP TABLE responder');
    }
}
