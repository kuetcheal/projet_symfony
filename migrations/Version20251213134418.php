<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251213134418 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout de la relation OneToOne Client → User via user_id (suppression définitive du password côté client)';
    }

    public function up(Schema $schema): void
    {
        //  IMPORTANT :
        // - La colonne user_id EXISTE DÉJÀ dans ta table client (vue dans phpMyAdmin)
        // - On NE TOUCHE PAS à password (déjà supprimé)

        // 1️ Ajout de la clé étrangère client.user_id → user.id
        $this->addSql(
            'ALTER TABLE client 
             ADD CONSTRAINT FK_C7440455A76ED395 
             FOREIGN KEY (user_id) REFERENCES user (id)'
        );

        // 2️ Index UNIQUE pour garantir le OneToOne
        $this->addSql(
            'CREATE UNIQUE INDEX UNIQ_C7440455A76ED395 ON client (user_id)'
        );
    }

    public function down(Schema $schema): void
    {
        // Suppression propre de la relation
        $this->addSql('ALTER TABLE client DROP FOREIGN KEY FK_C7440455A76ED395');
        $this->addSql('DROP INDEX UNIQ_C7440455A76ED395 ON client');
    }
}
