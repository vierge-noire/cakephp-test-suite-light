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
use CakephpTestSuiteLight\Sniffer\DriverTraits\SqliteSnifferTrait;

class SqliteTriggerBasedTableSniffer extends BaseTriggerBasedTableSniffer
{
    use SqliteSnifferTrait;

    /**
     * @return string
     */
    private function getDirtyTableCollectorName(): string
    {
        return ($this->isInTempMode() ? 'temp.' : '') . self::DIRTY_TABLE_COLLECTOR;
    }

    /**
     * @inheritDoc
     */
    public function truncateDirtyTables(): void
    {
        $tables = $this->getDirtyTables();

        // If a dirty table got dropped, it should be ignored
        $tables = array_intersect($tables, $this->getAllTables(true));

        if (empty($tables)) {
            return;
        }

        $this->getConnection()->disableConstraints(function (Connection $connection) use ($tables) {
            foreach ($tables as $table) {
                $connection->execute("DELETE FROM {$table}");
                try {
                    $connection->execute("DELETE FROM sqlite_sequence WHERE name = '{$table}'");
                } catch (\PDOException $e) {}
            }
        });

        $dirtyTable = $this->getDirtyTableCollectorName();
        try {
            $this->getConnection()->execute("DELETE FROM {$dirtyTable}");
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
        $temporary = $this->isInTempMode() ? 'TEMPORARY' : '';
        $schemaName = $this->isInTempMode() ? 'temp.' : '';

        $stmts = [];
        foreach ($this->getAllTablesExceptPhinxlogs() as $table) {
            if ($table === $dirtyTable) {
                continue;
            }
            $stmts[] = "
            CREATE {$temporary} TRIGGER {$triggerPrefix}{$table} AFTER INSERT ON `$table` 
                BEGIN
                    INSERT OR IGNORE INTO {$dirtyTable} VALUES ('{$table}'), ('{$schemaName}{$dirtyTable}');
                END;
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
     * @inheritDoc
     */
    public function markAllTablesAsDirty(): void
    {
        $tables = $this->getAllTablesExceptPhinxlogs();
        $dirtyTable = self::DIRTY_TABLE_COLLECTOR;
        $tables[] = $dirtyTable;

        $stmt = "INSERT OR IGNORE INTO {$dirtyTable} VALUES ('" . implode("'), ('", $tables) . "')";
        $this->getConnection()->execute($stmt);
    }
}