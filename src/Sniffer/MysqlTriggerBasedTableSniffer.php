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

class MysqlTriggerBasedTableSniffer extends BaseTableSniffer implements TriggerBasedTableSnifferInterface
{
    /**
     * @inheritDoc
     */
    public function truncateDirtyTables(): void
    {
        $this->getConnection()->execute('CALL TruncateDirtyTables();');
    }

    /**
     * @inheritDoc
     */
    public function fetchAllTables(): array
    {
        return $this->fetchQuery("
            SELECT table_name
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = DATABASE();
        ");
    }

    /**
     * @inheritDoc
     */
    public function dropTables(array $tables): void
    {
        $this->removeDirtyTableCollectorFromArray($tables);

        if (empty($tables)) {
            return;
        }

        $this->getConnection()->disableConstraints(function (Connection $connection) use ($tables) {
            $connection->transactional(function(Connection $connection) use ($tables) {
                $connection->execute(
                    $this->implodeSpecial(
                        'DROP TABLE IF EXISTS `',
                        $tables,
                        '`;'
                    )
                );
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
    public function setup(): void
    {
        parent::setup();

        // create dirty tables collector
        $this->createDirtyTableCollector();

        // create triggers
        $this->createTriggers();

        $dirtyTable = self::DIRTY_TABLE_COLLECTOR;
        // Collect all statements and run them in one transaction
        $stmts = [];

        // create truncate procedure
        $createTruncateProcedureStmt = "
            DROP PROCEDURE IF EXISTS TruncateDirtyTables;
            CREATE PROCEDURE TruncateDirtyTables()
            BEGIN
                DECLARE current_table_name VARCHAR(128);
                DECLARE finished INTEGER DEFAULT 0;
                DECLARE dirty_table_cursor CURSOR FOR
                    SELECT dt.table_name FROM {$dirtyTable} dt
                    INNER JOIN information_schema.TABLES info_schema on dt.table_name = info_schema.TABLE_NAME
                    WHERE info_schema.table_schema = DATABASE();                    
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
        ";
        $stmts[] = $createTruncateProcedureStmt;

        // Run all statements in one transaction
        $this->getConnection()->transactional(function(Connection $connection) use ($stmts) {
            foreach ($stmts as $stmt) {
                $connection->execute($stmt);
            }
        });
    }

    /**
     * @inheritDoc
     */
    public function getTriggers(): array
    {
        $triggers = $this->fetchQuery("
            SHOW triggers
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
    public function dropTriggers(): void
    {
        $triggers = $this->getTriggers();
        if (empty($triggers)) {
            return;
        }

        $stmts = $this->implodeSpecial(
            "DROP TRIGGER ",
            $triggers,
            ";"
        );
        $this->getConnection()->execute($stmts);
    }
}