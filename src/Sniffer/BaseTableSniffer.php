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
     * Truncate all the tables found in the dirty table collector
     * @return void
     */
    abstract public function truncateDirtyTables();

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
    abstract public function dropTables(array $tables);

    /**
     * BaseTableTruncator constructor.
     * @param ConnectionInterface $connection
     */
    public function __construct(ConnectionInterface $connection)
    {
        $this->connection = $connection;
        $this->setup();
    }

    /**
     * Setup method
     * @return void
     */
    public function setup()
    {
        $this->getAllTables(true);
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
    public function setConnection(ConnectionInterface $connection)
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
        return $this->fetchQuery("SELECT table_name FROM " . TriggerBasedTableSnifferInterface::DIRTY_TABLE_COLLECTOR);
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
     * The dirty table collector should never be dropped
     * This method helps removing it from a list of tables
     * @param array $tables
     * @return void
     */
    public function removeDirtyTableCollectorFromArray(array &$tables)
    {
        if (($key = array_search(TriggerBasedTableSnifferInterface::DIRTY_TABLE_COLLECTOR, $tables)) !== false) {
            unset($tables[$key]);
        }
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
    public function createDirtyTableCollector()
    {
        $dirtyTable = TriggerBasedTableSnifferInterface::DIRTY_TABLE_COLLECTOR;
        $this->getConnection()->execute("
            CREATE TABLE IF NOT EXISTS {$dirtyTable} (
            table_name VARCHAR(128) PRIMARY KEY
            );
        ");
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
}