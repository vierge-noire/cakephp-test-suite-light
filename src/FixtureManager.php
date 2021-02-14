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
use Cake\Datasource\ConnectionInterface;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\Fixture\FixtureManager as BaseFixtureManager;
use CakephpTestSuiteLight\Sniffer\SnifferRegistry;
use Exception;
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
    private static $aliasConnectionIsLoaded = false;

    /**
     * @var array|null
     */
    private $activeConnections;

    /**
     * @param string $name
     * @return ConnectionInterface
     */
    public function getConnection($name = 'test')
    {
        return ConnectionManager::get($name);
    }

    /**
     * @return void
     */
    public function aliasConnections()
    {
        if (!self::$aliasConnectionIsLoaded) {
            $this->_aliasConnections();
            self::$aliasConnectionIsLoaded = true;
        }
    }

    /**
     * Scan all test connections and truncate the dirty tables
     * @return void
     */
    public function truncateDirtyTables()
    {
        foreach ($this->getActiveConnections() as $connection) {
            SnifferRegistry::get($connection)->truncateDirtyTables();
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
        }

        if ((ConnectionManager::getConfig($connectionName)['skipInTestSuiteLight'] ?? false) === true) {
            return true;
        }

        if ($connectionName === 'test' || strpos($connectionName, 'test_') === 0) {
            return false;
        }

        return true;
    }

    /**
     * Get the appropriate sniffer and drop all tables
     * @param string $connectionName
     * @return void
     */
    public function dropTables(string $connectionName)
    {
        SnifferRegistry::get($connectionName)->dropTables(
            SnifferRegistry::get($connectionName)->fetchAllTables()
        );
    }

    /**
     * Initialize all connections used by the manager
     * @return array
     */
    public function fetchActiveConnections(): array
    {
        $connections = ConnectionManager::configured();
        $ignoredConnections = Configure::read('TestSuiteLightIgnoredConnections', []);
        foreach ($connections as $i => $connectionName) {
            if ($this->skipConnection($connectionName, $ignoredConnections)) {
                unset($connections[$i]);
            }
        }
        return $this->activeConnections = $connections;
    }

    /**
     * If not yet set, fetch the active connections
     * Those are the connections that are neither ignored,
     * nor irrelevant (debug_kit, non-test DBs etc...)
     * @return array
     */
    public function getActiveConnections(): array
    {
        return $this->activeConnections ?? $this->fetchActiveConnections();
    }

    /**
     * Insert fixture data.
     *
     * @param \Cake\TestSuite\TestCase $test The test to inspect for fixture loading.
     * @return void
     * @throws \Cake\Core\Exception\Exception When fixture records cannot be inserted.
     * @throws \RuntimeException
     */
    public function load($test)
    {
        if (!property_exists($test, 'fixtures')) {
            return;
        }

        $fixtures = $test->fixtures;
        if (!$fixtures || !$test->autoFixtures) {
            return;
        }

        try {
            foreach ($this->_fixtureConnections($fixtures) as $conn => $fixtures) {
                $connection = ConnectionManager::get($conn);
                $logQueries = $connection->isQueryLoggingEnabled();

                if ($logQueries && !$this->_debug) {
                    $connection->disableQueryLogging();
                }
                $connection->transactional(function (ConnectionInterface $connection) use ($fixtures, $test) {
                    $connection->disableConstraints(function (ConnectionInterface $connection) use ($fixtures, $test) {
                        foreach ($fixtures as $fixture) {
                            try {
                                $fixture->insert($connection);
                            } catch (\PDOException $e) {
                                $msg = sprintf(
                                    'Unable to insert fixture "%s" in "%s" test case: ' . "\n" . '%s',
                                    get_class($fixture),
                                    get_class($test),
                                    $e->getMessage()
                                );
                                throw new Exception($msg, 0, $e);
                            }
                        }
                    });
                });
                if ($logQueries) {
                    $connection->enableQueryLogging(true);
                }
            }
        } catch (\PDOException $e) {
            $msg = sprintf(
                'Unable to insert fixtures for "%s" test case. %s',
                get_class($test),
                $e->getMessage()
            );
            throw new \RuntimeException($msg, 0, $e);
        }
    }

    public function loadSingle($name, $db = null, $dropTables = true)
    {
        if (!isset($this->_fixtureMap[$name])) {
            throw new \UnexpectedValueException(sprintf('Referenced fixture class %s not found', $name));
        }

        $fixture = $this->_fixtureMap[$name];
        if (!$db) {
            $db = ConnectionManager::get($fixture->connection());
        }

        $db->disableConstraints(function (ConnectionInterface $db) use ($fixture) {
            try {
                $fixture->insert($db);
            } catch (\PDOException $e) {
                $msg = sprintf(
                    'Unable to insert fixture "%s": ' . "\n" . '%s',
                    get_class($fixture),
                    $e->getMessage()
                );
                throw new Exception($msg, 0, $e);
            }
        });
    }
}
