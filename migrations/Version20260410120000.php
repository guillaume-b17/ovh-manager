<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260410120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Table des codes de connexion envoyés par e-mail (OTP).';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof PostgreSQLPlatform) {
            $this->addSql('CREATE TABLE email_login_challenge (
            id SERIAL NOT NULL,
            email VARCHAR(180) NOT NULL,
            code_hash VARCHAR(255) NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            consumed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            attempts SMALLINT DEFAULT 0 NOT NULL,
            PRIMARY KEY(id)
        )');
        } elseif ($platform instanceof AbstractMySQLPlatform) {
            $this->addSql('CREATE TABLE email_login_challenge (
            id INT AUTO_INCREMENT NOT NULL,
            email VARCHAR(180) NOT NULL,
            code_hash VARCHAR(255) NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            expires_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            consumed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            attempts SMALLINT DEFAULT 0 NOT NULL,
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        } else {
            throw new \RuntimeException(sprintf('Plateforme SQL non gérée pour cette migration : %s', $platform::class));
        }

        $this->addSql('CREATE INDEX idx_email_login_challenge_email ON email_login_challenge (email)');
        $this->addSql('CREATE INDEX idx_email_login_challenge_expires ON email_login_challenge (expires_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE email_login_challenge');
    }
}
