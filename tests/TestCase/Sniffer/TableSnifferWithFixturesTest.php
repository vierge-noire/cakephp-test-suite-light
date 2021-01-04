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


use Cake\Database\Driver\Sqlite;
use Cake\Datasource\ConnectionManager;
use Cake\Datasource\EntityInterface;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;
use CakephpTestSuiteLight\Sniffer\BaseTableSniffer;
use CakephpTestSuiteLight\Sniffer\BaseTriggerBasedTableSniffer;
use CakephpTestSuiteLight\Sniffer\SnifferRegistry;
use CakephpTestSuiteLight\Test\TestUtil;
use CakephpTestSuiteLight\Test\Traits\ArrayComparerTrait;
use CakephpTestSuiteLight\Test\Traits\SnifferHelperTrait;
use TestApp\Model\Table\CitiesTable;
use TestApp\Model\Table\CountriesTable;
use TestApp\Test\Fixture\CitiesFixture;
use TestApp\Test\Fixture\CountriesFixture;

class TableSnifferWithFixturesTest extends TestCase
{
    use ArrayComparerTrait;
    use SnifferHelperTrait;

    public $fixtures = [
        // The order here is important
        CountriesFixture::class,
        CitiesFixture::class,
    ];

    /**
     * @var BaseTableSniffer
     */
    public $TableSniffer;

    /**
     * @var CountriesTable
     */
    public $Countries;

    /**
     * @var CitiesTable
     */
    public $Cities;

    public function setUp(): void
    {
        $this->TableSniffer = SnifferRegistry::get('test');
        $this->Countries = TableRegistry::getTableLocator()->get('Countries');
        $this->Cities = TableRegistry::getTableLocator()->get('Cities');

        $this->activateForeignKeysOnSqlite();
    }

    public function tearDown(): void
    {
        unset($this->TableSniffer);
        unset($this->Countries);
        unset($this->Cities);
        ConnectionManager::drop('test_dummy_connection');
        
        parent::tearDown();
    }

    /**
     * Find dirty tables
     * Countries is dirty, Cities is empty
     */
    public function testGetDirtyTables()
    {
        $expected = [
            'countries',
            'cities',
        ];
        if ($this->TableSniffer->implementsTriggers()) {
            if ($this->driverIs('Sqlite') && $this->TableSniffer->isInTempMode()) {
                $expected[] = 'temp.' . BaseTriggerBasedTableSniffer::DIRTY_TABLE_COLLECTOR;
            } else {
                $expected[] = BaseTriggerBasedTableSniffer::DIRTY_TABLE_COLLECTOR;
            }
        }

        $this->createCountry();
        $found = $this->TableSniffer->getDirtyTables();
        $this->assertArraysHaveSameContent($expected, $found);
    }

    /**
     * This list will need to be maintained as new tables are created or removed
     */
    public function testGetAllTables()
    {
        $found = $this->TableSniffer->fetchAllTables();
        $expected = [
            'cities',
            'countries',
            'phinxlog',
        ];
        if ($this->TableSniffer->implementsTriggers() && $this->TableSniffer->isInMainMode()) {
            $expected[] = BaseTriggerBasedTableSniffer::DIRTY_TABLE_COLLECTOR;
        }

        $this->assertArraysHaveSameContent($expected, $found);
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

    private function activateForeignKeysOnSqlite() {
        $connection = ConnectionManager::get('test');
        if ($connection->config()['driver'] === Sqlite::class) {
            $connection->execute('PRAGMA foreign_keys = ON;' );
        }
    }

    private function createCountry(): EntityInterface
    {
        $country = $this->Countries->newEntity([
            'name' => 'Foo',
        ]);
        return $this->Countries->saveOrFail($country);
    }

    private function createCity(): EntityInterface
    {
        $city = $this->Cities->newEntity([
            'uuid_primary_key' => TestUtil::makeUuid(),
            'id_primary_key' => rand(1, 99999999),
            'name' => 'Foo',
            'country_id' => $this->createCountry()->id
        ]);
        return $this->Cities->saveOrFail($city);
    }

    /**
     * Given: A city with a country
     * When: Country gets deleted
     * Then: Throw an error
     */
    public function testThatForeignKeysConstrainWorksOnDelete()
    {
        $this->expectException(\PDOException::class);
        // City with no country will not save
        $city = $this->createCity();
        $country = $this->Countries->get($city->country_id);
        $this->Countries->delete($country);
    }

    public function testTruncateWithForeignKey()
    {
        $this->createCity();

        $this->TableSniffer->truncateDirtyTables();

        $this->assertSame(
            0,
            $this->Cities->find()->count() + $this->Countries->find()->count()
        );
    }

    public function testGetAndDropTriggers()
    {
        $this->skipIf(!$this->TableSniffer->implementsTriggers());

        $found = $this->TableSniffer->getTriggers();
        $expected = [
            'dirty_table_spy_countries',
            'dirty_table_spy_cities',
        ];

        $this->assertArraysHaveSameContent($expected, $found);

        $this->TableSniffer->dropTriggers();
        $expected = [];
        $found = $this->TableSniffer->getTriggers();
        $this->assertArraysHaveSameContent($expected, $found);

        $this->TableSniffer->start();
    }
}