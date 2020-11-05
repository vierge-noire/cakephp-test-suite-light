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
namespace CakephpTestSuiteLight\Test\TestCase\Sniffer;


use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\TestCase;
use CakephpTestSuiteLight\FixtureManager;
use CakephpTestSuiteLight\Sniffer\BaseTableSniffer;
use CakephpTestSuiteLight\Sniffer\TriggerBasedTableSnifferInterface;
use CakephpTestSuiteLight\Test\Traits\ArrayComparerTrait;
use TestApp\Test\Fixture\CitiesFixture;
use TestApp\Test\Fixture\CountriesFixture;

class TableSnifferTest extends TestCase
{
    use ArrayComparerTrait;

    public $fixtures = [
        // The order here is important
        CountriesFixture::class,
        CitiesFixture::class,

    ];

    public $autoFixtures = false;

    /**
     * @var BaseTableSniffer
     */
    public $TableSniffer;

    /**
     * @var FixtureManager
     */
    public $FixtureManager;

    public function setUp()
    {
        $this->FixtureManager = new FixtureManager();
        $this->TableSniffer = $this->FixtureManager->getSniffer('test');
    }

    public function tearDown()
    {
        unset($this->TableSniffer);
        unset($this->FixtureManager);

        ConnectionManager::drop('test_dummy_connection');
        
        parent::tearDown();
    }

    private function createNonExistentConnection()
    {
        $config = ConnectionManager::getConfig('test');
        $config['database'] = 'dummy_database';
        ConnectionManager::setConfig('test_dummy_connection', $config);
    }

    private function driverIs(string $driver): bool
    {
        return ConnectionManager::getConfig('test')['driver'] === 'Cake\Database\Driver\\' . $driver;
    }

    public function dataProviderOfDirtyTables()
    {
        return [
            [true],
            [false],
        ];
    }

    /**
     * All tables should be clean before every test
     * @dataProvider dataProviderOfDirtyTables
     */
    public function testGetDirtyTablesWithLoadFixtures(bool $loadFixtures)
    {
        if ($loadFixtures) {
            $this->loadFixtures();
            $expected = $this->TableSniffer->implementsTriggers() ? 3 : 2;      // Expect countries, cities, but also the dirty_table table
        } else {
            $expected = 0;
        }
        $this->assertSame($expected, count($this->TableSniffer->getDirtyTables()));
    }

    /**
     * All tables should be clean before every test
     * @dataProvider dataProviderOfDirtyTables
     */
    public function testGetDirtyTablesWithLoadOneCity(bool $loadFixtures)
    {
        if ($loadFixtures) {
            $this->skipIf(
                $this->driverIs('Postgres'),
                "Foreign key constrains on countries will lead to failed insert on Postgres."
            );
            $this->loadFixtures('Cities');
            $expected = $this->TableSniffer->implementsTriggers() ? 2 : 1;      // Expect cities, but also the dirty_table table
        } else {
            $expected = 0;
        }
        $this->assertSame($expected, count($this->TableSniffer->getDirtyTables()));
    }

    /**
     * All tables should be clean before every test
     * @dataProvider dataProviderOfDirtyTables
     */
    public function testGetDirtyTablesWithLoadOneCountry(bool $loadFixtures)
    {
        if ($loadFixtures)
        {
            $this->loadFixtures('Countries');
            $expected = $this->TableSniffer->implementsTriggers() ? 2 : 1;      // Expect cities, but also the dirty_table table
        } else {
            $expected = 0;
        }
        $this->assertSame($expected, count($this->TableSniffer->getDirtyTables()));
    }

    /**
     * If a DB is not created, the sniffers should throw an exception
     */
    public function testGetSnifferOnNonExistentDB()
    {
        $this->createNonExistentConnection();

        if ($this->driverIs('Sqlite')) {
            $this->assertTrue(true);
        } else {
            $this->expectException(\Exception::class);
        }
        $this->FixtureManager->getSniffer('test_dummy_connection');
    }

    public function testImplodeSpecial()
    {
        $array = ['foo', 'bar'];
        $glueBefore = 'ABC';
        $glueAfter = 'DEF';
        $expect = 'ABCfooDEFABCbarDEF';
        $this->assertSame($expect, $this->TableSniffer->implodeSpecial($glueBefore, $array, $glueAfter));
    }

    public function testCheckTriggersAfterSetup()
    {
        $this->skipIf(!$this->TableSniffer->implementsTriggers());

        $expected = [
            'dirty_table_spy_cities',
            'dirty_table_spy_countries',
        ];
        if ($this->driverIs('Mysql')) {
            $found = $this->TableSniffer->fetchQuery('SHOW TRIGGERS');
        } elseif ($this->driverIs('Postgres')) {
            $found = $this->TableSniffer->fetchQuery('SELECT tgname FROM pg_trigger');
            $expected[] = 'dirty_table_spy_' . TriggerBasedTableSnifferInterface::DIRTY_TABLE_COLLECTOR;
        } elseif ($this->driverIs('Sqlite')) {
            $found = $this->TableSniffer->fetchQuery('SELECT name FROM sqlite_master WHERE type = "trigger"');
            $expected[] = 'dirty_table_spy_' . TriggerBasedTableSnifferInterface::DIRTY_TABLE_COLLECTOR;
        }

        foreach ($expected as $trigger) {
            $this->assertSame(true, in_array($trigger, $found), "Trigger $trigger was not found");
        }
    }

    public function testGetAllTablesExceptPhinxlogs()
    {
        $found = $this->TableSniffer->getAllTablesExceptPhinxlogs();
        $expected = [
            'cities',
            'countries',
        ];

        if ($this->TableSniffer->implementsTriggers()) {
            $expected[] = TriggerBasedTableSnifferInterface::DIRTY_TABLE_COLLECTOR;
        } else {
            $this->TableSniffer->removeDirtyTableCollectorFromArray($found);
        }

        $this->assertArraysHaveSameContent($expected, $found);
    }
}