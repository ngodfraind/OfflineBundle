<?php

namespace Claroline\OfflineBundle\Migrations\pdo_sqlsrv;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated migration based on mapping information: modify it with caution
 *
 * Generation date: 2014/04/21 01:58:43
 */
class Version20140421135837 extends AbstractMigration
{
    public function up(Schema $schema)
    {
        $this->addSql("
            CREATE TABLE claro_role_creation (
                id INT IDENTITY NOT NULL,
                role_id INT,
                creation_date DATETIME2(6) NOT NULL,
                PRIMARY KEY (id)
            )
        ");
        $this->addSql("
            CREATE INDEX IDX_5005B245D60322AC ON claro_role_creation (role_id)
        ");
        $this->addSql("
            ALTER TABLE claro_role_creation
            ADD CONSTRAINT FK_5005B245D60322AC FOREIGN KEY (role_id)
            REFERENCES claro_role (id)
        ");
    }

    public function down(Schema $schema)
    {
        $this->addSql("
            DROP TABLE claro_role_creation
        ");
    }
}
