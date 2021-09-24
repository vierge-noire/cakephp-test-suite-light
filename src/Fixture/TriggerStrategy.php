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
namespace CakephpTestSuiteLight\Fixture;

use Cake\Core\Configure;
use Cake\Datasource\ConnectionInterface;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\Fixture\FixtureHelper;
use Cake\TestSuite\Fixture\FixtureStrategyInterface;
use CakephpTestSuiteLight\Sniffer\SnifferRegistry;

/**
 * Fixture strategy that wraps fixtures in a transaction that is rolled back
 * after each test.
 *
 * Any test that calls Connection::rollback(true) will break this strategy.
 */
class TriggerStrategy implements FixtureStrategyInterface
{
    /**
     * @var array|null
     */
    protected $activeConnections;

    /**
     * @inheritDoc
     */
    public function setupTest(array $fixtureNames): void
    {
        $this->truncateDirtyTables();
        $helper = new FixtureHelper();
        $fixtures = $helper->loadFixtures($fixtureNames);
        $helper->insert($fixtures);
    }

    /**
     * @inheritDoc
     */
    public function teardownTest(): void
    {
        // We do nothing here
    }

    /**
     * @param string $name
     * @return ConnectionInterface
     */
    public function getConnection($name = 'test')
    {
        return ConnectionManager::get($name);
    }

    /**
     * Scan all test connections and truncate the dirty tables
     * @return void
     */
    public function truncateDirtyTables(): void
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
     * @throws \RuntimeException
     */
    public function getActiveConnections(): array
    {
        return $this->activeConnections ?? $this->fetchActiveConnections();
    }
}
