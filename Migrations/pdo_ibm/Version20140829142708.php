<?php

namespace UJM\ExoBundle\Migrations\pdo_ibm;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated migration based on mapping information: modify it with caution
 *
 * Generation date: 2014/08/29 02:27:11
 */
class Version20140829142708 extends AbstractMigration
{
    public function up(Schema $schema)
    {
        $this->addSql("
            ALTER TABLE ujm_proposal 
            ADD COLUMN label_id INTEGER DEFAULT NULL
        ");
        $this->addSql("
            ALTER TABLE ujm_proposal 
            ADD CONSTRAINT FK_2672B44B33B92F39 FOREIGN KEY (label_id) 
            REFERENCES ujm_label (id)
        ");
        $this->addSql("
            CREATE INDEX IDX_2672B44B33B92F39 ON ujm_proposal (label_id)
        ");
    }

    public function down(Schema $schema)
    {
        $this->addSql("
            ALTER TABLE ujm_proposal 
            DROP COLUMN label_id
        ");
        $this->addSql("
            ALTER TABLE ujm_proposal 
            DROP FOREIGN KEY FK_2672B44B33B92F39
        ");
        $this->addSql("
            DROP INDEX IDX_2672B44B33B92F39
        ");
    }
}