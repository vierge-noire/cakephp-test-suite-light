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
namespace CakephpTestSuiteLight\Test\TestCase;


use Cake\Core\Configure;
use Cake\Database\Driver\Mysql;
use Cake\Database\Driver\Postgres;
use Cake\Database\Driver\Sqlite;
use Cake\Datasource\ConnectionManager;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;
use CakephpTestSuiteLight\FixtureManager;
use CakephpTestSuiteLight\Sniffer\MysqlTriggerBasedTableSniffer;
use CakephpTestSuiteLight\Sniffer\PostgresTriggerBasedTableSniffer;
use CakephpTestSuiteLight\Sniffer\SqliteTriggerBasedTableSniffer;
use TestApp\Model\Table\CountriesTable;
use TestApp\Test\Fixture\CitiesFixture;
use TestApp\Test\Fixture\CountriesFixture;

class FixtureManagerTest extends TestCase
{
    /**
     * @var FixtureManager
     */
    public $FixtureManager;

    /**
     * @var CountriesTable
     */
    public $Countries;

    public $fixtures = [
        // The order here is important
        CountriesFixture::class,
        CitiesFixture::class,
    ];

    public $autoFixtures = false;

    public function setUp(): void
    {
        $this->FixtureManager = new FixtureManager();
        $this->Countries = TableRegistry::getTableLocator()->get('Countries');
    }

    public function tearDown(): void
    {
        unset($this->FixtureManager);
        unset($this->Countries);
    }

    public function testTablePopulation()
    {
        $this->loadFixtures();
        $this->assertEquals(
            1,
            $this->Countries->find()->count()
        );
        $this->assertEquals(
            1,
            $this->Countries->find()->firstOrFail()->id,
            'The id should be equal to 1. There might be an error in the truncation of the authors table, or of the tables in general'
        );
    }

    public function testTablesEmptyOnStart()
    {
        $tables = ['cities', 'countries'];

        foreach ($tables as $table) {
            $Table = TableRegistry::getTableLocator()->get($table);
            $this->assertEquals(
                0,
                $Table->find()->count(),
                'Make sure that both tables were created by fixture loading by a previous test.'
            );
        }
    }

    public function testConnectionIsTest()
    {
        $this->assertEquals(
            'test',
            $this->Countries->getConnection()->config()['name']
        );
    }

    public function dataProviderTestLoadDefaultSniffer()
    {
        return [
            [Mysql::class, MysqlTriggerBasedTableSniffer::class],
            [Sqlite::class, SqliteTriggerBasedTableSniffer::class],
            [Postgres::class, PostgresTriggerBasedTableSniffer::class],
        ];
    }

    /**
     * @param $driver
     * @param $sniffer
     * @dataProvider dataProviderTestLoadDefaultSniffer
     */
    public function testGetDefaultTableSniffers($driver, $sniffer)
    {
        $act = $this->FixtureManager->getDefaultTableSniffers()[$driver];
        $this->assertEquals($sniffer, $act);
    }

    public function testLoadSnifferFromConfigFile()
    {
        $expected = '\testTableSniffer';
        $this->FixtureManager->loadConfig();
        $conf = Configure::readOrFail('TestSuiteLightSniffers.\testDriver');
        $this->assertEquals($expected, $conf);
    }

    public function testGetConnectionSnifferNameOnNonExistingConnection()
    {
        $this->expectException(\PHPUnit\Framework\Exception::class);
        $this->FixtureManager->getConnectionSnifferName('dummy');
    }

    public function testGetConnectionSnifferNameOnConnection()
    {
        $sniffer = 'FooSniffer';
        $connectionName = 'testGetConnectionSnifferNameOnConnection';
        $testConfig = ConnectionManager::getConfig('test');
        $testConfig['tableSniffer'] = $sniffer;
        ConnectionManager::setConfig($connectionName, $testConfig);
        $act = $this->FixtureManager->getConnectionSnifferName($connectionName);
        $this->assertSame($sniffer, $act);
        ConnectionManager::drop($connectionName);
    }

    public function testFetchActiveConnections()
    {
        $this->FixtureManager->fetchActiveConnections();
        $connections = $this->FixtureManager->getActiveConnections();

        $this->assertSame(1, count($connections));
        $this->assertSame(true, in_array('test', $connections));
    }

    public function testSkipIgnoredConnection()
    {
        $ignored = 'FooConnection';

        $act = $this->FixtureManager->skipConnection($ignored, [$ignored]);
        $this->assertSame(true, $act);

        $act = $this->FixtureManager->skipConnection('test', [$ignored]);
        $this->assertSame(false, $act);

        $act = $this->FixtureManager->skipConnection('testconnection', [$ignored]);
        $this->assertSame(true, $act);

        $act = $this->FixtureManager->skipConnection('test_connection', [$ignored]);
        $this->assertSame(false, $act);

        $connectionName = 'test_ConnectionToBeIgnored';
        $testConfig = ConnectionManager::getConfig('test');
        $testConfig['skipInTestSuiteLight'] = true;
        ConnectionManager::setConfig($connectionName, $testConfig);
        $act = $this->FixtureManager->skipConnection($connectionName, [$ignored]);
        $this->assertSame(true, $act);
        ConnectionManager::drop($connectionName);
    }
}