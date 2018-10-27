<?php declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20181002163522 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'sqlite', 'Migration can only be executed safely on \'sqlite\'.');

        $this->addSql('CREATE TABLE luminaire_status (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, code INTEGER NOT NULL, message VARCHAR(255) DEFAULT NULL)');
        $this->addSql('CREATE TABLE luminaire_luminaire_status (luminaire_id INTEGER NOT NULL, luminaire_status_id INTEGER NOT NULL, PRIMARY KEY(luminaire_id, luminaire_status_id))');
        $this->addSql('CREATE INDEX IDX_F478AB4CDC90A29E ON luminaire_luminaire_status (luminaire_id)');
        $this->addSql('CREATE INDEX IDX_F478AB4CE254CE66 ON luminaire_luminaire_status (luminaire_status_id)');
        $this->addSql('DROP INDEX IDX_46DC8952DC90A29E');
        $this->addSql('CREATE TEMPORARY TABLE __temp__pcb AS SELECT id, luminaire_id, crc, serial, n, type FROM pcb');
        $this->addSql('DROP TABLE pcb');
        $this->addSql('CREATE TABLE pcb (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, luminaire_id INTEGER DEFAULT NULL, crc VARCHAR(6) DEFAULT NULL COLLATE BINARY, serial VARCHAR(10) DEFAULT NULL COLLATE BINARY, n INTEGER DEFAULT NULL, type INTEGER DEFAULT NULL, CONSTRAINT FK_46DC8952DC90A29E FOREIGN KEY (luminaire_id) REFERENCES luminaire (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO pcb (id, luminaire_id, crc, serial, n, type) SELECT id, luminaire_id, crc, serial, n, type FROM __temp__pcb');
        $this->addSql('DROP TABLE __temp__pcb');
        $this->addSql('CREATE INDEX IDX_46DC8952DC90A29E ON pcb (luminaire_id)');
        $this->addSql('DROP INDEX IDX_A2F98E47DC90A29E');
        $this->addSql('CREATE TEMPORARY TABLE __temp__channel AS SELECT id, luminaire_id, channel, i_peek, wave_length, led_type, pcb, manuf FROM channel');
        $this->addSql('DROP TABLE channel');
        $this->addSql('CREATE TABLE channel (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, luminaire_id INTEGER DEFAULT NULL, channel INTEGER DEFAULT NULL, i_peek INTEGER DEFAULT NULL, wave_length INTEGER DEFAULT NULL, led_type VARCHAR(2) DEFAULT NULL COLLATE BINARY, pcb INTEGER DEFAULT NULL, manuf VARCHAR(1) DEFAULT NULL COLLATE BINARY, CONSTRAINT FK_A2F98E47DC90A29E FOREIGN KEY (luminaire_id) REFERENCES luminaire (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO channel (id, luminaire_id, channel, i_peek, wave_length, led_type, pcb, manuf) SELECT id, luminaire_id, channel, i_peek, wave_length, led_type, pcb, manuf FROM __temp__channel');
        $this->addSql('DROP TABLE __temp__channel');
        $this->addSql('CREATE INDEX IDX_A2F98E47DC90A29E ON channel (luminaire_id)');
        $this->addSql('ALTER TABLE luminaire ADD COLUMN serial VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE luminaire ADD COLUMN address VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'sqlite', 'Migration can only be executed safely on \'sqlite\'.');

        $this->addSql('DROP TABLE luminaire_status');
        $this->addSql('DROP TABLE luminaire_luminaire_status');
        $this->addSql('DROP INDEX IDX_A2F98E47DC90A29E');
        $this->addSql('CREATE TEMPORARY TABLE __temp__channel AS SELECT id, luminaire_id, channel, i_peek, wave_length, led_type, pcb, manuf FROM channel');
        $this->addSql('DROP TABLE channel');
        $this->addSql('CREATE TABLE channel (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, luminaire_id INTEGER DEFAULT NULL, channel INTEGER DEFAULT NULL, i_peek INTEGER DEFAULT NULL, wave_length INTEGER DEFAULT NULL, led_type VARCHAR(2) DEFAULT NULL, pcb INTEGER DEFAULT NULL, manuf VARCHAR(1) DEFAULT NULL)');
        $this->addSql('INSERT INTO channel (id, luminaire_id, channel, i_peek, wave_length, led_type, pcb, manuf) SELECT id, luminaire_id, channel, i_peek, wave_length, led_type, pcb, manuf FROM __temp__channel');
        $this->addSql('DROP TABLE __temp__channel');
        $this->addSql('CREATE INDEX IDX_A2F98E47DC90A29E ON channel (luminaire_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__luminaire AS SELECT id FROM luminaire');
        $this->addSql('DROP TABLE luminaire');
        $this->addSql('CREATE TABLE luminaire (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL)');
        $this->addSql('INSERT INTO luminaire (id) SELECT id FROM __temp__luminaire');
        $this->addSql('DROP TABLE __temp__luminaire');
        $this->addSql('DROP INDEX IDX_46DC8952DC90A29E');
        $this->addSql('CREATE TEMPORARY TABLE __temp__pcb AS SELECT id, luminaire_id, crc, serial, n, type FROM pcb');
        $this->addSql('DROP TABLE pcb');
        $this->addSql('CREATE TABLE pcb (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, luminaire_id INTEGER DEFAULT NULL, crc VARCHAR(6) DEFAULT NULL, serial VARCHAR(10) DEFAULT NULL, n INTEGER DEFAULT NULL, type INTEGER DEFAULT NULL)');
        $this->addSql('INSERT INTO pcb (id, luminaire_id, crc, serial, n, type) SELECT id, luminaire_id, crc, serial, n, type FROM __temp__pcb');
        $this->addSql('DROP TABLE __temp__pcb');
        $this->addSql('CREATE INDEX IDX_46DC8952DC90A29E ON pcb (luminaire_id)');
    }
}
