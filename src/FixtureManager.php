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
namespace CakephpTestSuiteLight;

use Cake\Core\Configure;
use Cake\Core\Exception\Exception;
use Cake\Datasource\ConnectionInterface;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\Fixture\FixtureManager as BaseFixtureManager;
use CakephpTestSuiteLight\Sniffer\BaseTableSniffer;
use CakephpTestSuiteLight\Sniffer\MysqlTableSniffer;
use CakephpTestSuiteLight\Sniffer\PostgresTableSniffer;
use CakephpTestSuiteLight\Sniffer\SqliteTableSniffer;
use function strpos;

/**
 * Class FixtureManager
 * @package CakephpTestSuiteLight
 */
class FixtureManager extends BaseFixtureManager
{
    /**
     * @var bool
     */
    private static $_configIsLoaded = false;

    /**
     * @var array
     */
    private $dirtyTables = [];

    /**
     * FixtureManager constructor.
     * The config file test_suite_light is being loaded
     */
    public function __construct()
    {
        $this
            ->initDb()
            ->loadConfig();
    }

    /**
     * @param string $name
     * @return ConnectionInterface
     */
    public function getConnection($name = 'test')
    {
        return ConnectionManager::get($name);
    }

    public function initDb(): FixtureManager
    {
        $this->_initDb();
        return $this;
    }

    public function aliasConnections()
    {
        $this->_aliasConnections();
    }

    public function getSniffer(string $connectionName): BaseTableSniffer
    {
        $connection = $this->getConnection($connectionName);
        $driver = $connection->config()['driver'];
        try {
            $snifferName = Configure::readOrFail('TestSuiteLightSniffers.' . $driver);
        } catch (\RuntimeException $e) {
            throw new \PHPUnit\Framework\Exception("The DB driver $driver is not being supported");
        }
        /** @var BaseTableSniffer $snifferName */
        return new $snifferName($connection);
    }

    /**
     * Scan all Test connections and truncate the dirty tables
     */
    public function truncateDirtyTables()
    {
        foreach ($this->getDirtyTables() as $connection => $dirtyTables) {
            $sniffer = $this->getSniffer($connection);
            $sniffer->truncateTables($sniffer->getDirtyTables());
        }
    }

    /**
     * @param string $connectionName
     * @param array  $ignoredConnections
     *
     * @return bool
     */
    public function skipConnection(string $connectionName, array $ignoredConnections): bool
    {
        // CakePHP 4 solves a DebugKit issue by creating an Sqlite connection
        // in tests/bootstrap.php. This connection should be ignored.
        if ($connectionName === 'test_debug_kit' || in_array($connectionName, $ignoredConnections)) {
            return true;
        } elseif ($connectionName === 'test' || strpos($connectionName, 'test_') === 0) {
            return false;
        }
        return true;
    }

    /**
     * Load the mapping between the database drivers
     * and the table truncators.
     * Add your own truncators for a driver not being covered by
     * the package in your test_suite_light.php config file
     */
    public function loadConfig(): FixtureManager
    {
        if (!self::$_configIsLoaded) {
            Configure::write([
                'TestSuiteLightSniffers' => $this->getDefaultTableSniffers()
            ]);
            try {
                Configure::load('test_suite_light');
            } catch (Exception $exception) {}
            self::$_configIsLoaded = true;
        }

        return $this;
    }

    /**
     * Table truncators provided by the package
     * @return array
     */
    private function getDefaultTableSniffers()
    {
        return [
            \Cake\Database\Driver\Mysql::class => MysqlTableSniffer::class,
            \Cake\Database\Driver\Sqlite::class => SqliteTableSniffer::class,
            \Cake\Database\Driver\Postgres::class => PostgresTableSniffer::class,
        ];
    }

    /**
     * Get the appropriate truncator and drop all tables
     * @param string $connectionName
     */
    public function dropTables(string $connectionName)
    {
        $this->getSniffer($connectionName)->dropTables(
            $this->getSniffer($connectionName)->getAllTables()
        );
    }

    /**
     * @return array
     */
    public function getDirtyTables(): array
    {
        return $this->dirtyTables;
    }

    /**
     *
     */
    public function collectDirtyTables(): FixtureManager
    {
        $ignoredConnections = Configure::read('TestSuiteLightIgnoredConnections', []);
        foreach (ConnectionManager::configured() as $connectionName) {
            if (!$this->skipConnection($connectionName, $ignoredConnections)) {
                $this->dirtyTables[$connectionName] = $this->getSniffer($connectionName)->getDirtyTables();
            }
        }
        return $this;
    }
}
