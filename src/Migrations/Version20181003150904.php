<?php declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20181003150904 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'sqlite', 'Migration can only be executed safely on \'sqlite\'.');

        $this->addSql('CREATE TABLE "group" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, label INTEGER NOT NULL, description VARCHAR(255) DEFAULT NULL)');
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
        $this->addSql('CREATE TEMPORARY TABLE __temp__luminaire AS SELECT id, serial, address FROM luminaire');
        $this->addSql('DROP TABLE luminaire');
        $this->addSql('CREATE TABLE luminaire (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, groupe_id INTEGER DEFAULT NULL, serial VARCHAR(255) DEFAULT NULL COLLATE BINARY, address VARCHAR(255) DEFAULT NULL COLLATE BINARY, CONSTRAINT FK_BF3BAD1B7A45358C FOREIGN KEY (groupe_id) REFERENCES "group" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO luminaire (id, serial, address) SELECT id, serial, address FROM __temp__luminaire');
        $this->addSql('DROP TABLE __temp__luminaire');
        $this->addSql('CREATE INDEX IDX_BF3BAD1B7A45358C ON luminaire (groupe_id)');
        $this->addSql('DROP INDEX IDX_F478AB4CE254CE66');
        $this->addSql('DROP INDEX IDX_F478AB4CDC90A29E');
        $this->addSql('CREATE TEMPORARY TABLE __temp__luminaire_luminaire_status AS SELECT luminaire_id, luminaire_status_id FROM luminaire_luminaire_status');
        $this->addSql('DROP TABLE luminaire_luminaire_status');
        $this->addSql('CREATE TABLE luminaire_luminaire_status (luminaire_id INTEGER NOT NULL, luminaire_status_id INTEGER NOT NULL, PRIMARY KEY(luminaire_id, luminaire_status_id), CONSTRAINT FK_F478AB4CDC90A29E FOREIGN KEY (luminaire_id) REFERENCES luminaire (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_F478AB4CE254CE66 FOREIGN KEY (luminaire_status_id) REFERENCES luminaire_status (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO luminaire_luminaire_status (luminaire_id, luminaire_status_id) SELECT luminaire_id, luminaire_status_id FROM __temp__luminaire_luminaire_status');
        $this->addSql('DROP TABLE __temp__luminaire_luminaire_status');
        $this->addSql('CREATE INDEX IDX_F478AB4CE254CE66 ON luminaire_luminaire_status (luminaire_status_id)');
        $this->addSql('CREATE INDEX IDX_F478AB4CDC90A29E ON luminaire_luminaire_status (luminaire_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'sqlite', 'Migration can only be executed safely on \'sqlite\'.');

        $this->addSql('DROP TABLE "group"');
        $this->addSql('DROP INDEX IDX_A2F98E47DC90A29E');
        $this->addSql('CREATE TEMPORARY TABLE __temp__channel AS SELECT id, luminaire_id, channel, i_peek, wave_length, led_type, pcb, manuf FROM channel');
        $this->addSql('DROP TABLE channel');
        $this->addSql('CREATE TABLE channel (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, luminaire_id INTEGER DEFAULT NULL, channel INTEGER DEFAULT NULL, i_peek INTEGER DEFAULT NULL, wave_length INTEGER DEFAULT NULL, led_type VARCHAR(2) DEFAULT NULL, pcb INTEGER DEFAULT NULL, manuf VARCHAR(1) DEFAULT NULL)');
        $this->addSql('INSERT INTO channel (id, luminaire_id, channel, i_peek, wave_length, led_type, pcb, manuf) SELECT id, luminaire_id, channel, i_peek, wave_length, led_type, pcb, manuf FROM __temp__channel');
        $this->addSql('DROP TABLE __temp__channel');
        $this->addSql('CREATE INDEX IDX_A2F98E47DC90A29E ON channel (luminaire_id)');
        $this->addSql('DROP INDEX IDX_BF3BAD1B7A45358C');
        $this->addSql('CREATE TEMPORARY TABLE __temp__luminaire AS SELECT id, serial, address FROM luminaire');
        $this->addSql('DROP TABLE luminaire');
        $this->addSql('CREATE TABLE luminaire (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, serial VARCHAR(255) DEFAULT NULL, address VARCHAR(255) DEFAULT NULL)');
        $this->addSql('INSERT INTO luminaire (id, serial, address) SELECT id, serial, address FROM __temp__luminaire');
        $this->addSql('DROP TABLE __temp__luminaire');
        $this->addSql('DROP INDEX IDX_F478AB4CDC90A29E');
        $this->addSql('DROP INDEX IDX_F478AB4CE254CE66');
        $this->addSql('CREATE TEMPORARY TABLE __temp__luminaire_luminaire_status AS SELECT luminaire_id, luminaire_status_id FROM luminaire_luminaire_status');
        $this->addSql('DROP TABLE luminaire_luminaire_status');
        $this->addSql('CREATE TABLE luminaire_luminaire_status (luminaire_id INTEGER NOT NULL, luminaire_status_id INTEGER NOT NULL, PRIMARY KEY(luminaire_id, luminaire_status_id))');
        $this->addSql('INSERT INTO luminaire_luminaire_status (luminaire_id, luminaire_status_id) SELECT luminaire_id, luminaire_status_id FROM __temp__luminaire_luminaire_status');
        $this->addSql('DROP TABLE __temp__luminaire_luminaire_status');
        $this->addSql('CREATE INDEX IDX_F478AB4CDC90A29E ON luminaire_luminaire_status (luminaire_id)');
        $this->addSql('CREATE INDEX IDX_F478AB4CE254CE66 ON luminaire_luminaire_status (luminaire_status_id)');
        $this->addSql('DROP INDEX IDX_46DC8952DC90A29E');
        $this->addSql('CREATE TEMPORARY TABLE __temp__pcb AS SELECT id, luminaire_id, crc, serial, n, type FROM pcb');
        $this->addSql('DROP TABLE pcb');
        $this->addSql('CREATE TABLE pcb (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, luminaire_id INTEGER DEFAULT NULL, crc VARCHAR(6) DEFAULT NULL, serial VARCHAR(10) DEFAULT NULL, n INTEGER DEFAULT NULL, type INTEGER DEFAULT NULL)');
        $this->addSql('INSERT INTO pcb (id, luminaire_id, crc, serial, n, type) SELECT id, luminaire_id, crc, serial, n, type FROM __temp__pcb');
        $this->addSql('DROP TABLE __temp__pcb');
        $this->addSql('CREATE INDEX IDX_46DC8952DC90A29E ON pcb (luminaire_id)');
    }
}
