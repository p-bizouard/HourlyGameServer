<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210302120430 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE server_log (id INT AUTO_INCREMENT NOT NULL, server_id INT DEFAULT NULL, type VARCHAR(255) NOT NULL, message VARCHAR(255) NOT NULL, created DATETIME NOT NULL, updated DATETIME NOT NULL, INDEX IDX_B1F6629F1844E6B7 (server_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE server_log ADD CONSTRAINT FK_B1F6629F1844E6B7 FOREIGN KEY (server_id) REFERENCES server (id)');
        $this->addSql('ALTER TABLE server ADD CONSTRAINT FK_5A6DD5F67E3C61F9 FOREIGN KEY (owner_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE server ADD CONSTRAINT FK_5A6DD5F6E48FD905 FOREIGN KEY (game_id) REFERENCES game (id)');
        $this->addSql('ALTER TABLE server ADD CONSTRAINT FK_5A6DD5F63A51721D FOREIGN KEY (instance_id) REFERENCES instance (id)');
        $this->addSql('ALTER TABLE server ADD CONSTRAINT FK_5A6DD5F6F771EEB8 FOREIGN KEY (last_history_id) REFERENCES server_history (id)');
        $this->addSql('ALTER TABLE server ADD CONSTRAINT FK_5A6DD5F68472B84F FOREIGN KEY (last_backup_id) REFERENCES server_backup (id)');
        $this->addSql('ALTER TABLE server ADD CONSTRAINT FK_5A6DD5F68F2C079 FOREIGN KEY (last_check_id) REFERENCES server_check (id)');
        $this->addSql('ALTER TABLE server_backup ADD CONSTRAINT FK_468CE86A1844E6B7 FOREIGN KEY (server_id) REFERENCES server (id)');
        $this->addSql('ALTER TABLE server_check ADD CONSTRAINT FK_9C0FCE1A1844E6B7 FOREIGN KEY (server_id) REFERENCES server (id)');
        $this->addSql('ALTER TABLE server_history ADD CONSTRAINT FK_55C46BF71844E6B7 FOREIGN KEY (server_id) REFERENCES server (id)');
        $this->addSql('ALTER TABLE server_history ADD CONSTRAINT FK_55C46BF73A51721D FOREIGN KEY (instance_id) REFERENCES instance (id)');
        $this->addSql('ALTER TABLE server_user ADD CONSTRAINT FK_613A7A9A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE server_user ADD CONSTRAINT FK_613A7A91844E6B7 FOREIGN KEY (server_id) REFERENCES server (id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE server_log');
        $this->addSql('ALTER TABLE server DROP FOREIGN KEY FK_5A6DD5F67E3C61F9');
        $this->addSql('ALTER TABLE server DROP FOREIGN KEY FK_5A6DD5F6E48FD905');
        $this->addSql('ALTER TABLE server DROP FOREIGN KEY FK_5A6DD5F63A51721D');
        $this->addSql('ALTER TABLE server DROP FOREIGN KEY FK_5A6DD5F6F771EEB8');
        $this->addSql('ALTER TABLE server DROP FOREIGN KEY FK_5A6DD5F68472B84F');
        $this->addSql('ALTER TABLE server DROP FOREIGN KEY FK_5A6DD5F68F2C079');
        $this->addSql('ALTER TABLE server_backup DROP FOREIGN KEY FK_468CE86A1844E6B7');
        $this->addSql('ALTER TABLE server_check DROP FOREIGN KEY FK_9C0FCE1A1844E6B7');
        $this->addSql('ALTER TABLE server_history DROP FOREIGN KEY FK_55C46BF71844E6B7');
        $this->addSql('ALTER TABLE server_history DROP FOREIGN KEY FK_55C46BF73A51721D');
        $this->addSql('ALTER TABLE server_user DROP FOREIGN KEY FK_613A7A9A76ED395');
        $this->addSql('ALTER TABLE server_user DROP FOREIGN KEY FK_613A7A91844E6B7');
    }
}
