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
use CakephpTestSuiteLight\Sniffer\DriverTraits\PostgresSnifferTrait;

class PostgresTriggerBasedTableSniffer extends BaseTriggerBasedTableSniffer
{
    use PostgresSnifferTrait;

    /**
     * @inheritDoc
     */
    public function truncateDirtyTables(): void
    {
        try {
            $this->getConnection()->transactional(function (Connection $connection) {
                $connection->execute('CALL TruncateDirtyTables();');
                $connection->execute('TRUNCATE TABLE ' . self::DIRTY_TABLE_COLLECTOR . ' RESTART IDENTITY CASCADE;');
            });
        } catch (\Exception $e) {
            // The dirty table collector might not be found because the session
            // was interrupted.
            $this->restart();
        }
    }

    /**
     * @inheritDoc
     */
    public function createTriggers(): void
    {
        // drop triggers
        $this->dropTriggers();

        $dirtyTable = self::DIRTY_TABLE_COLLECTOR;
        $triggerPrefix = self::TRIGGER_PREFIX;

        $stmts = [];
        foreach ($this->getAllTablesExceptPhinxlogs() as $table) {
            if ($table === $dirtyTable) {
                continue;
            }
            $stmts[] = "
                CREATE OR REPLACE FUNCTION mark_table_{$table}_as_dirty() RETURNS TRIGGER LANGUAGE PLPGSQL AS $$
                DECLARE
                    spy_is_inactive {$dirtyTable}%ROWTYPE;                    
                BEGIN
                    SELECT * FROM {$dirtyTable} WHERE table_name = '{$table}' LIMIT 1 INTO spy_is_inactive;                                                 
                    IF NOT FOUND THEN
                        INSERT INTO {$dirtyTable} (table_name) VALUES ('{$table}'), ('{$dirtyTable}') ON CONFLICT DO NOTHING;                        
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
    public function start(): void
    {
        parent::start();

        $this->createDirtyTableCollector();
        $this->createTriggers();
        $this->createTruncateDirtyTablesProcedure();
        $this->cleanAllTables();
    }

    /**
     * @inheritDoc
     */
    public function shutdown(): void
    {
        parent::shutdown();

        $this->dropTriggers();
        $this->dropDirtyTableCollector();
    }

    /**
     * @return void
     */
    public function createTruncateDirtyTablesProcedure(): void
    {
        $dirtyTable = self::DIRTY_TABLE_COLLECTOR;
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
    public function markAllTablesAsDirty(): void
    {
        $tables = $this->getAllTablesExceptPhinxlogs();
        $dirtyTable = self::DIRTY_TABLE_COLLECTOR;
        $tables[] = $dirtyTable;

        $stmt = "INSERT INTO {$dirtyTable} VALUES ('" . implode("'), ('", $tables) . "') ON CONFLICT DO NOTHING";
        $this->getConnection()->execute($stmt);
    }
}