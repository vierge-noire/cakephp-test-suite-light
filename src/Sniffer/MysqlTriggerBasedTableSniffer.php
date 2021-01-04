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


use CakephpTestSuiteLight\Sniffer\DriverTraits\MysqlSnifferTrait;

class MysqlTriggerBasedTableSniffer extends BaseTriggerBasedTableSniffer
{
    use MysqlSnifferTrait;

    /**
     * @inheritDoc
     */
    public function truncateDirtyTables(): void
    {
        try {
            $this->getConnection()->execute('CALL TruncateDirtyTables();');
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

        $stmts = "";
        foreach ($this->getAllTablesExceptPhinxlogs() as $table) {
            if ($table === $dirtyTable) {
                continue;
            }
            $stmts .= "       
            CREATE TRIGGER {$triggerPrefix}{$table} AFTER INSERT ON `{$table}`
            FOR EACH ROW                
                INSERT IGNORE INTO {$dirtyTable} (table_name) VALUES ('{$table}'), ('{$dirtyTable}');                
            ";
        }

        if ($stmts !== '') {
            $this->getConnection()->execute($stmts);
        }
    }

    /**
     * @inheritDoc
     */
    public function start(): void
    {
        parent::start();

        // create dirty tables collector
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
            DROP PROCEDURE IF EXISTS TruncateDirtyTables;
            CREATE PROCEDURE TruncateDirtyTables()
            BEGIN
                DECLARE current_table_name VARCHAR(128);
                DECLARE finished INTEGER DEFAULT 0;
                DECLARE dirty_table_cursor CURSOR FOR
                    SELECT dt.table_name FROM {$dirtyTable} dt;                    
                DECLARE CONTINUE HANDLER FOR NOT FOUND SET finished = 1;
            
                SET FOREIGN_KEY_CHECKS=0;
                OPEN dirty_table_cursor;
                truncate_tables: LOOP
                    FETCH dirty_table_cursor INTO current_table_name;
                    IF finished = 1 THEN
                        LEAVE truncate_tables;
                    END IF;
                    SET @create_trigger_statement = CONCAT('TRUNCATE TABLE `', current_table_name, '`;');
                    PREPARE stmt FROM @create_trigger_statement;
                    EXECUTE stmt;
                    DEALLOCATE PREPARE stmt;
                END LOOP truncate_tables;
                CLOSE dirty_table_cursor;
                            
                SET FOREIGN_KEY_CHECKS=1;
            END
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

        $stmt = "INSERT IGNORE INTO {$dirtyTable} VALUES ('" . implode("'), ('", $tables) . "')";
        $this->getConnection()->execute($stmt);
    }
}