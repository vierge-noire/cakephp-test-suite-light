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
use CakephpTestMigrator\Migrator;
use CakephpTestMigrator\SchemaCleaner;
use CakephpTestSuiteLight\FixtureManager;
use CakephpTestSuiteLight\Sniffer\BaseTableSniffer;
use CakephpTestSuiteLight\Sniffer\BaseTriggerBasedTableSniffer;
use CakephpTestSuiteLight\Sniffer\SnifferRegistry;
use CakephpTestSuiteLight\Test\Traits\ArrayComparerTrait;
use CakephpTestSuiteLight\Test\Traits\InsertTestDataTrait;
use CakephpTestSuiteLight\Test\Traits\SnifferHelperTrait;
use TestApp\Test\Fixture\CitiesFixture;
use TestApp\Test\Fixture\CountriesFixture;

class TableSnifferTest extends TestCase
{
    use ArrayComparerTrait;
    use InsertTestDataTrait;
    use SnifferHelperTrait;

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
        $this->TableSniffer = SnifferRegistry::get('test');
    }

    public function tearDown()
    {
        unset($this->TableSniffer);

        ConnectionManager::drop('test_dummy_connection');

        parent::tearDown();
    }

    private function createNonExistentConnection()
    {
        $config = ConnectionManager::getConfig('test');
        $config['database'] = 'dummy_database';
        ConnectionManager::setConfig('test_dummy_connection', $config);
    }

    public function dataProviderOfDirtyTables()
    {
        return [
//            [true],
            [false],
        ];
    }

    /**
     * All tables should be clean before every test
     * @dataProvider dataProviderOfDirtyTables
     * @param bool $loadFixtures
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
     * @param bool $loadFixtures
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
     * @param bool $loadFixtures
     */
    public function testGetDirtyTablesWithLoadOneCountry(bool $loadFixtures)
    {
        if ($loadFixtures)
        {
            $expected = [
                'countries',
            ];
            $this->loadFixtures('Countries');
            if ($this->TableSniffer->implementsTriggers()) {
                if ($this->driverIs('Sqlite') && $this->TableSniffer->isInTempMode()) {
                    $expected[] = 'temp.' . BaseTriggerBasedTableSniffer::DIRTY_TABLE_COLLECTOR;
                } else {
                    $expected[] = BaseTriggerBasedTableSniffer::DIRTY_TABLE_COLLECTOR;
                }
            }
        } else {
            $expected = [];
        }
        $this->assertArraysHaveSameContent($expected, $this->TableSniffer->getDirtyTables());
    }

    /**
     * If a DB is not created, the sniffers should not throw on exception.
     */
    public function testGetSnifferOnNonExistentDB()
    {
        $this->createNonExistentConnection();
        $sniffer = SnifferRegistry::get('test_dummy_connection');
        $this->assertInstanceOf(BaseTableSniffer::class, $sniffer);
    }

    public function testImplodeSpecial()
    {
        $array = ['foo', 'bar'];
        $glueBefore = 'ABC';
        $glueAfter = 'DEF';
        $expect = 'ABCfooDEFABCbarDEF';
        $this->assertSame($expect, $this->TableSniffer->implodeSpecial($glueBefore, $array, $glueAfter));
    }

    public function testCheckTriggersAfterStart()
    {
        $this->skipUnless($this->TableSniffer->implementsTriggers());

        $expected = [
            'dirty_table_spy_cities',
            'dirty_table_spy_countries',
        ];
        if ($this->driverIs('Mysql')) {
            $found = $this->TableSniffer->fetchQuery('SHOW TRIGGERS');
        } elseif ($this->driverIs('Postgres')) {
            $found = $this->TableSniffer->fetchQuery('SELECT tgname FROM pg_trigger');
        } elseif ($this->driverIs('Sqlite')) {
            if ($this->TableSniffer->implementsTriggers() && $this->TableSniffer->isInTempMode()) {
                $found = $this->TableSniffer->fetchQuery('SELECT name FROM sqlite_temp_master WHERE type = "trigger"');
            } else {
                $found = $this->TableSniffer->fetchQuery('SELECT name FROM sqlite_master WHERE type = "trigger"');
            }

        }

        foreach ($expected as $trigger) {
            $this->assertSame(true, in_array($trigger, $found), "Trigger $trigger was not found");
        }
    }

    public function testGetAllTablesExceptPhinxlogs()
    {
        $found = $this->TableSniffer->getAllTablesExceptPhinxlogs(true);
        $expected = [
            'cities',
            'countries',
        ];
        if ($this->TableSniffer->implementsTriggers() && $this->TableSniffer->isInMainMode()) {
            $expected[] = BaseTriggerBasedTableSniffer::DIRTY_TABLE_COLLECTOR;
        }

        $this->assertArraysHaveSameContent($expected, $found);
    }

    public function testGetAllTablesExceptPhinxlogsAndCollector()
    {
        $this->skipUnless($this->TableSniffer->implementsTriggers());

        $found = $this->TableSniffer->getAllTablesExceptPhinxlogsAndCollector(true);
        $expected = [
            'cities',
            'countries',
        ];

        $this->assertArraysHaveSameContent($expected, $found);
    }

    public function testMarkAllTablesAsDirtyOnEmptyDirtyTableCollector()
    {
        $this->skipUnless($this->TableSniffer->implementsTriggers());

        $dirtyTables = $this->TableSniffer->getDirtyTables();
        $this->assertSame([], $dirtyTables);

        $this->TableSniffer->markAllTablesAsDirty();

        $dirtyTables = $this->TableSniffer->getDirtyTables();
        $this->assertArraysHaveSameContent([
            'cities',
            'countries',
        ], $dirtyTables);
    }

    public function testMarkAllTablesAsDirtyOnNotEmptyDirtyTableCollector()
    {
        $this->skipUnless($this->TableSniffer->implementsTriggers());

        $this->createCountry();

        $dirtyTables = $this->TableSniffer->getDirtyTables();
        $this->assertArraysHaveSameContent([
            'countries',
        ], $dirtyTables);

        $this->TableSniffer->markAllTablesAsDirty();

        $dirtyTables = $this->TableSniffer->getDirtyTables();
        $this->assertArraysHaveSameContent([
            'cities',
            'countries',
        ], $dirtyTables);
    }

    public function testGetTriggers()
    {
        if ($this->TableSniffer->implementsTriggers()) {
            $expect = [
                'dirty_table_spy_cities',
                'dirty_table_spy_countries',
            ];
        } else {
            $expect = [];
        }

        $this->assertArraysHaveSameContent($expect, $this->TableSniffer->getTriggers());
    }

    public function testCreateTriggers()
    {
        $this->skipUnless($this->TableSniffer->implementsTriggers());

        $this->expectException(\PDOException::class);
        $this->TableSniffer->createTriggers();

        $triggers = $this->TableSniffer->getTriggers();
        $this->assertArraysHaveSameContent([
            'dirty_table_spy_cities',
            'dirty_table_spy_countries',
        ], $triggers);
    }

    public function testSwitchMode()
    {
        $this->skipUnless($this->TableSniffer->implementsTriggers());
        $mode = $this->TableSniffer->getMode();

        foreach ([1, 2, 3] as $i) {
            $this->TableSniffer->activateTempMode();
            $this->assertSame(false, in_array(BaseTriggerBasedTableSniffer::DIRTY_TABLE_COLLECTOR, $this->TableSniffer->getAllTables(true)));

            $this->TableSniffer->activateMainMode();
            $this->assertSame(true, in_array(BaseTriggerBasedTableSniffer::DIRTY_TABLE_COLLECTOR, $this->TableSniffer->getAllTables(true)));
        }

        $this->TableSniffer->setMode($mode);
    }

    public function testRecreateDirtyTableCollectorAfterDrop()
    {
        $this->skipUnless($this->TableSniffer->implementsTriggers());

        (new SchemaCleaner)->drop('test');
        Migrator::migrate();

        $tables = $this->TableSniffer->getAllTablesExceptPhinxlogs(true);
        $this->assertSame(false, in_array(BaseTriggerBasedTableSniffer::DIRTY_TABLE_COLLECTOR, $tables));

        $this->TableSniffer->init();

        $tables = $this->TableSniffer->getAllTablesExceptPhinxlogs(true);


        $exp = !getenv('SNIFFERS_IN_TEMP_MODE');
        $this->assertSame($exp, in_array(BaseTriggerBasedTableSniffer::DIRTY_TABLE_COLLECTOR, $tables));
    }
}
