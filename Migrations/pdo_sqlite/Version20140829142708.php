<?php

namespace UJM\ExoBundle\Migrations\pdo_sqlite;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated migration based on mapping information: modify it with caution
 *
 * Generation date: 2014/08/29 02:27:10
 */
class Version20140829142708 extends AbstractMigration
{
    public function up(Schema $schema)
    {
        $this->addSql("
            DROP INDEX IDX_B797C100FAB79C10
        ");
        $this->addSql("
            CREATE TEMPORARY TABLE __temp__ujm_proposal AS 
            SELECT id, 
            interaction_matching_id, 
            value 
            FROM ujm_proposal
        ");
        $this->addSql("
            DROP TABLE ujm_proposal
        ");
        $this->addSql("
            CREATE TABLE ujm_proposal (
                id INTEGER NOT NULL, 
                interaction_matching_id INTEGER DEFAULT NULL, 
                label_id INTEGER DEFAULT NULL, 
                value CLOB NOT NULL, 
                PRIMARY KEY(id), 
                CONSTRAINT FK_B797C100FAB79C10 FOREIGN KEY (interaction_matching_id) 
                REFERENCES ujm_interaction_matching (id) NOT DEFERRABLE INITIALLY IMMEDIATE, 
                CONSTRAINT FK_2672B44B33B92F39 FOREIGN KEY (label_id) 
                REFERENCES ujm_label (id) NOT DEFERRABLE INITIALLY IMMEDIATE
            )
        ");
        $this->addSql("
            INSERT INTO ujm_proposal (
                id, interaction_matching_id, value
            ) 
            SELECT id, 
            interaction_matching_id, 
            value 
            FROM __temp__ujm_proposal
        ");
        $this->addSql("
            DROP TABLE __temp__ujm_proposal
        ");
        $this->addSql("
            CREATE INDEX IDX_B797C100FAB79C10 ON ujm_proposal (interaction_matching_id)
        ");
        $this->addSql("
            CREATE INDEX IDX_2672B44B33B92F39 ON ujm_proposal (label_id)
        ");
    }

    public function down(Schema $schema)
    {
        $this->addSql("
            DROP INDEX IDX_2672B44BFAB79C10
        ");
        $this->addSql("
            DROP INDEX IDX_2672B44B33B92F39
        ");
        $this->addSql("
            CREATE TEMPORARY TABLE __temp__ujm_proposal AS 
            SELECT id, 
            interaction_matching_id, 
            value 
            FROM ujm_proposal
        ");
        $this->addSql("
            DROP TABLE ujm_proposal
        ");
        $this->addSql("
            CREATE TABLE ujm_proposal (
                id INTEGER NOT NULL, 
                interaction_matching_id INTEGER DEFAULT NULL, 
                value CLOB NOT NULL, 
                PRIMARY KEY(id), 
                CONSTRAINT FK_2672B44BFAB79C10 FOREIGN KEY (interaction_matching_id) 
                REFERENCES ujm_interaction_matching (id) NOT DEFERRABLE INITIALLY IMMEDIATE
            )
        ");
        $this->addSql("
            INSERT INTO ujm_proposal (
                id, interaction_matching_id, value
            ) 
            SELECT id, 
            interaction_matching_id, 
            value 
            FROM __temp__ujm_proposal
        ");
        $this->addSql("
            DROP TABLE __temp__ujm_proposal
        ");
        $this->addSql("
            CREATE INDEX IDX_2672B44BFAB79C10 ON ujm_proposal (interaction_matching_id)
        ");
    }
}