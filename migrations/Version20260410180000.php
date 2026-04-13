<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260410180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Modèles de message répondeur (portail) + téléphone agence.';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof PostgreSQLPlatform) {
            $this->addSql('CREATE TABLE organization_setting (id SERIAL NOT NULL, agency_phone VARCHAR(80) DEFAULT NULL, PRIMARY KEY(id))');
            $this->addSql('CREATE TABLE responder_message_template (id SERIAL NOT NULL, code VARCHAR(50) NOT NULL, label VARCHAR(120) NOT NULL, content TEXT NOT NULL, sort_order INT NOT NULL, enabled BOOLEAN DEFAULT true NOT NULL, PRIMARY KEY(id))');
            $this->addSql('CREATE UNIQUE INDEX uniq_responder_template_code ON responder_message_template (code)');
        } elseif ($platform instanceof AbstractMySQLPlatform) {
            $this->addSql('CREATE TABLE organization_setting (id INT AUTO_INCREMENT NOT NULL, agency_phone VARCHAR(80) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
            $this->addSql('CREATE TABLE responder_message_template (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(50) NOT NULL, label VARCHAR(120) NOT NULL, content LONGTEXT NOT NULL, sort_order INT NOT NULL, enabled TINYINT(1) DEFAULT 1 NOT NULL, PRIMARY KEY(id), UNIQUE INDEX uniq_responder_template_code (code)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        } else {
            throw new \RuntimeException('Plateforme SQL non gérée pour cette migration : '.$platform::class);
        }

        $this->addSql('INSERT INTO organization_setting (id, agency_phone) VALUES (1, NULL)');
        if ($platform instanceof PostgreSQLPlatform) {
            $this->addSql("SELECT setval(pg_get_serial_sequence('organization_setting', 'id'), GREATEST((SELECT COALESCE(MAX(id), 1) FROM organization_setting), 1))");
        }

        $absence = <<<'TXT'
Bonjour,

Je suis absent pour une courte durée et vous répondrai dès mon retour.

Période : du {date_debut} au {date_fin}.
Pour toute urgence : {telephone_agence}

Cordialement
TXT;
        $conges = <<<'TXT'
Bonjour,

Je suis en congés (du {date_debut} au {date_fin}).

Pour toute urgence, merci de contacter l’agence au {telephone_agence}.

Cordialement
TXT;

        $q = fn (string $s): string => $this->connection->quote($s);

        if ($platform instanceof PostgreSQLPlatform) {
            $this->addSql(sprintf(
                'INSERT INTO responder_message_template (code, label, content, sort_order, enabled) VALUES (%s, %s, %s, 0, true)',
                $q('absence'),
                $q('Absence courte'),
                $q($absence),
            ));
            $this->addSql(sprintf(
                'INSERT INTO responder_message_template (code, label, content, sort_order, enabled) VALUES (%s, %s, %s, 10, true)',
                $q('conges'),
                $q('Congés'),
                $q($conges),
            ));
        } else {
            $this->addSql(sprintf(
                'INSERT INTO responder_message_template (code, label, content, sort_order, enabled) VALUES (%s, %s, %s, 0, 1)',
                $q('absence'),
                $q('Absence courte'),
                $q($absence),
            ));
            $this->addSql(sprintf(
                'INSERT INTO responder_message_template (code, label, content, sort_order, enabled) VALUES (%s, %s, %s, 10, 1)',
                $q('conges'),
                $q('Congés'),
                $q($conges),
            ));
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE responder_message_template');
        $this->addSql('DROP TABLE organization_setting');
    }
}
