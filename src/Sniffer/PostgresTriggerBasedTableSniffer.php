<?php
declare(strict_types=1);

/**
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) 2020 Juan Pablo Ramirez and Nicolas Masson
 * @link          https://webrider.de/
 * @since         1.0.0
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace CakephpTestSuiteLight\Sniffer;


use Cake\Database\Connection;

class PostgresTriggerBasedTableSniffer extends BaseTableSniffer implements TriggerBasedTableSnifferInterface
{
    /**
     * @inheritDoc
     */
    public function truncateDirtyTables()
    {
        $this->getConnection()->transactional(function (Connection $connection) {
            $connection->execute('CALL TruncateDirtyTables();');
            $connection->execute('TRUNCATE TABLE ' . self::DIRTY_TABLE_COLLECTOR . ' RESTART IDENTITY CASCADE;');
        });
    }

    /**
     * @inheritDoc
     */
    public function fetchAllTables(): array
    {
        return $this->fetchQuery("            
            SELECT table_name
            FROM information_schema.tables
            WHERE table_schema = 'public'            
        ");
    }

    /**
     * @inheritDoc
     */
    public function dropTables(array $tables)
    {
        $this->removeDirtyTableCollectorFromArray($tables);

        if (empty($tables)) {
            return;
        }

        $this->getConnection()->disableConstraints(function (Connection $connection) use ($tables) {
            $connection->transactional(function(Connection $connection) use ($tables) {
                foreach ($tables as $table) {
                    $connection->execute(
                        'DROP TABLE IF EXISTS "' . $table  . '" CASCADE;'
                    );
                }
                // Truncate dirty table collector
                $connection
                    ->newQuery()
                    ->delete(self::DIRTY_TABLE_COLLECTOR)
                    ->execute();
            });
        });
    }

    /**
     * @inheritDoc
     */
    public function createTriggers()
    {
        // drop triggers
        $this->dropTriggers();

        $dirtyTable = self::DIRTY_TABLE_COLLECTOR;
        $triggerPrefix = self::TRIGGER_PREFIX;

        $stmts = [];
        foreach ($this->getAllTablesExceptPhinxlogs() as $table) {
            $stmts[] = "
                CREATE OR REPLACE FUNCTION mark_table_{$table}_as_dirty() RETURNS TRIGGER LANGUAGE PLPGSQL AS $$
                DECLARE
                    spy_is_inactive {$dirtyTable}%ROWTYPE;                    
                BEGIN
                    SELECT * FROM {$dirtyTable} WHERE table_name = '{$table}' LIMIT 1 INTO spy_is_inactive;                                                 
                    IF NOT FOUND THEN                
                        INSERT INTO {$dirtyTable} (table_name) VALUES ('{$table}');                        
                    END IF;                                                               
                    RETURN NEW;
                END;                
                $$
                ";

            $stmts[] = "                
                CREATE TRIGGER {$triggerPrefix}{$table} AFTER INSERT ON {$table}                
                FOR EACH ROW
                    EXECUTE PROCEDURE mark_table_{$table}_as_dirty();                                                                                                                    
                ";
        }
        foreach ($stmts as $stmt) {
            $this->getConnection()->execute($stmt);
        }
    }

    /**
     * @inheritDoc
     */
    public function setup()
    {
        parent::setup();

        $dirtyTable = self::DIRTY_TABLE_COLLECTOR;

        // create dirty tables collector
        $this->createDirtyTableCollector();

        // create triggers
        $this->createTriggers();

        // create truncate procedure
        $this->getConnection()->execute("
            CREATE OR REPLACE PROCEDURE TruncateDirtyTables() AS $$
            DECLARE
                _rec    record;
            BEGIN           
                FOR _rec IN (
                    SELECT  * FROM {$dirtyTable} dt
                    INNER JOIN information_schema.tables info_schema on dt.table_name = info_schema.table_name                    
                    WHERE info_schema.table_schema = 'public'
                    AND dt.table_name != '{$dirtyTable}'
                ) LOOP
                    BEGIN
                        EXECUTE 'TRUNCATE TABLE ' || _rec.table_name || ' RESTART IDENTITY CASCADE';
                    END;
                END LOOP;                                
                RETURN;                                
            END $$ LANGUAGE plpgsql;
        ");
    }

    /**
     * @inheritDoc
     */
    public function getTriggers(): array
    {
        $triggerPrefix = self::TRIGGER_PREFIX;
        $triggers = $this->fetchQuery("
            SELECT tgname
            FROM pg_trigger
            WHERE tgname LIKE '{$triggerPrefix}%'
        ");

        foreach ($triggers as $k => $trigger) {
            if (strpos($trigger, self::TRIGGER_PREFIX) !== 0) {
                unset($triggers[$k]);
            }
        }

        return (array)$triggers;
    }

    /**
     * @inheritDoc
     */
    public function dropTriggers()
    {
        $triggers = $this->getTriggers();
        if (empty($triggers)) {
            return;
        }

        foreach ($triggers as $trigger) {
            $table = substr($trigger, strlen(self::TRIGGER_PREFIX));
            $this->getConnection()->execute("DROP TRIGGER {$trigger} ON {$table};");
        }
    }
}