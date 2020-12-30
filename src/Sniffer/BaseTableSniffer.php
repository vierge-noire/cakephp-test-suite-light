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


use Cake\Database\Exception;
use Cake\Datasource\ConnectionInterface;

abstract class BaseTableSniffer
{
    /**
     * @var ConnectionInterface
     */
    protected $connection;

    /**
     * @var array|null
     */
    protected $allTables;

    /**
     * @var string
     */
    protected $mode;

    /**
     * Truncate all the tables found in the dirty table collector
     * @return void
     */
    abstract public function truncateDirtyTables(): void;

    /**
     * List all tables
     * @return array
     */
    abstract public function fetchAllTables(): array;

    /**
     * Drop tables passed as a parameter
     * @param array $tables
     * @return void
     */
    abstract public function dropTables(array $tables): void;

    /**
     * Get triggers relative to the database dirty table collector
     * @return array
     */
    abstract public function getTriggers(): array;

    /**
     * Drop triggers relative to the database dirty table collector
     * @return void
     */
    abstract public function dropTriggers();

    /**
     * BaseTableTruncator constructor.
     * @param ConnectionInterface $connection
     */
    public function __construct(ConnectionInterface $connection)
    {
        $this->connection = $connection;
        if ($this->implementsTriggers()) {
            // Per default the dirty table collator is temporary
            $this->mode = TriggerBasedTableSnifferInterface::TEMP_MODE;
        }
        $this->start();
    }

    /**
     * Start spying
     * @return void
     */
    public function start(): void
    {
        $this->getAllTables(true);
    }

    /**
     * Stop spying
     * @return void
     */
    public function shutdown(): void
    {}

    /**
     * Stop spying and restart
     * Useful if the schema or the
     * dirty table collector changed
     * @return void
     */
    public function restart(): void
    {
        $this->shutdown();
        $this->start();
    }

    /**
     * @return ConnectionInterface
     */
    public function getConnection(): ConnectionInterface
    {
        return $this->connection;
    }

    /**
     * @param ConnectionInterface $connection
     */
    public function setConnection(ConnectionInterface $connection): void
    {
        $this->connection = $connection;
    }

    /**
     * Find all tables where an insert happened
     * This also includes empty tables, where a delete
     * was performed after an insert
     * @return array
     */
    public function getDirtyTables(): array
    {
        try {
            return $this->fetchQuery("SELECT table_name FROM " . TriggerBasedTableSnifferInterface::DIRTY_TABLE_COLLECTOR);
        } catch (\Exception $e) {
            $this->restart();
            return $this->getAllTablesExceptPhinxlogs(true);
        }
    }

    /**
     * Execute a query returning a list of table
     * In case where the query fails because the database queried does
     * not exist, an exception is thrown.
     *
     * @param string $query
     *
     * @return array
     */
    public function fetchQuery(string $query): array
    {
        try {
            $tables = $this->getConnection()->execute($query)->fetchAll();
            if ($tables === false) {
                throw new \Exception("Failing query: $query");
            }
        } catch (\Exception $e) {
            $name = $this->getConnection()->configName();
            $db = $this->getConnection()->config()['database'];
            var_dump($e->getMessage());
            throw new Exception("Error in the connection '$name'. Is the database '$db' created and accessible?");
        }

        foreach ($tables as $i => $val) {
            $tables[$i] = $val[0] ?? $val['name'];
        }

        return $tables;
    }

    /**
     * @param string $glueBefore
     * @param array  $array
     * @param string $glueAfter
     *
     * @return string
     */
    public function implodeSpecial(string $glueBefore, array $array, string $glueAfter): string
    {
        return $glueBefore . implode($glueAfter.$glueBefore, $array) . $glueAfter;
    }

    /**
     * Get all tables except the phinx tables
     * * @param bool $forceFetch
     * @return array
     */
    public function getAllTablesExceptPhinxlogs(bool $forceFetch = false): array
    {
        $allTables = $this->getAllTables($forceFetch);
        foreach ($allTables as $i => $table) {
            if (strpos($table, 'phinxlog') !== false) {
                unset($allTables[$i]);
            }
        }
        return $allTables;
    }

    /**
     * @param bool $forceFetch
     * @return array
     */
    public function getAllTables(bool $forceFetch = false): array
    {
        if (is_null($this->allTables) || $forceFetch) {
            $this->allTables = $this->fetchAllTables();
        }
        return $this->allTables;
    }

    /**
     * Create the table gathering the dirty tables
     * @return void
     */
    public function createDirtyTableCollector(): void
    {
        $temporary = $this->isInTempMode() ? 'TEMPORARY' : '';
        $dirtyTable = TriggerBasedTableSnifferInterface::DIRTY_TABLE_COLLECTOR;

        $this->getConnection()->execute("
            CREATE {$temporary} TABLE IF NOT EXISTS {$dirtyTable} (
                table_name VARCHAR(128) PRIMARY KEY
            );
        ");
    }

    /**
     * Drop the table gathering the dirty tables
     * @return void
     */
    public function dropDirtyTableCollector()
    {
        $dirtyTable = TriggerBasedTableSnifferInterface::DIRTY_TABLE_COLLECTOR;
        $this->getConnection()->execute("DROP TABLE IF EXISTS {$dirtyTable}");
    }

    /**
     * The dirty table collector being temporary, ensure that all tables are clean when starting the suite
     */
    public function cleanAllTables(): void
    {
        if ($this->implementsTriggers() && method_exists($this, 'markAllTablesAsDirty')) {
            // Ensure the sniffer starts on an empty DB
            $this->markAllTablesAsDirty();
            $this->truncateDirtyTables();
        }
    }

    /**
     * Checks if the present class implements triggers
     * @return bool
     */
    public function implementsTriggers(): bool
    {
        $class = new \ReflectionClass($this);
        return $class->implementsInterface(TriggerBasedTableSnifferInterface::class);
    }

    /**
     * @return void
     */
    public function activateMainMode(): void
    {
        $this->setMode(TriggerBasedTableSnifferInterface::MAIN_MODE);
    }

    /**
     * @return void
     */
    public function activateTempMode(): void
    {
        $this->setMode(TriggerBasedTableSnifferInterface::TEMP_MODE);
    }

    /**
     * @param string $mode
     * @return void
     */
    public function setMode(string $mode): void
    {
        if ($this->mode === $mode) {
            return;
        }
        $this->mode = $mode;
        $this->restart();
    }

    public function getMode(): string
    {
        if (!$this->implementsTriggers()) {
            return '';
        }
        return $this->mode;
    }

    public function isInTempMode(): bool
    {
        return ($this->getMode() === TriggerBasedTableSnifferInterface::TEMP_MODE);
    }

    public function isInMainMode(): bool
    {
        return ($this->getMode() === TriggerBasedTableSnifferInterface::MAIN_MODE);
    }
}