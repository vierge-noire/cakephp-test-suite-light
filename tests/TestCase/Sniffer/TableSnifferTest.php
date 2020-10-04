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
namespace CakephpTestSuiteLight\Test\TestCase\TestSuite\Sniffer;


use Cake\Database\Exception;
use Cake\Datasource\ConnectionManager;

use Cake\TestSuite\TestCase;
use CakephpTestSuiteLight\FixtureManager;
use CakephpTestSuiteLight\Sniffer\BaseTableSniffer;
use CakephpTestSuiteLight\Sniffer\SqliteTableSniffer;
use CakephpTestSuiteLight\Test\Fixture\CitiesFixture;
use CakephpTestSuiteLight\Test\Fixture\CountriesFixture;

class TableSnifferTest extends TestCase
{
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

    public function setUp(): void
    {
        $this->FixtureManager = new FixtureManager();
        $this->TableSniffer = $this->FixtureManager->getSniffer('test');
    }

    public function tearDown(): void
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

    /**
     * Following the convention, the TableSniffers must be the name of
     * the driver (e.g. Mysql)  + "TableSniffer"
     */
    public function testTableSnifferFinder()
    {
        $driver = explode('\\', getenv('DB_DRIVER'));
        $driver = array_pop($driver);
        $expectedClass = '\CakephpTestSuiteLight\Sniffer\\' . $driver . 'TableSniffer';
        $this->assertInstanceOf($expectedClass, $this->TableSniffer);
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
    public function testGetDirtyTables(bool $loadFixtures)
    {
        if ($loadFixtures)
        {
            $this->loadFixtures();
            $expected = 2;
        } else {
            $expected = 0;
        }
        $this->assertSame($expected, count($this->TableSniffer->getDirtyTables()));
    }

    /**
     * If a DB is not created, the sniffers should throw an exception
     */
    public function testGetDirtyTablesOnNonExistentDB()
    {
        $this->createNonExistentConnection();
        $sniffer = $this->FixtureManager->getSniffer('test_dummy_connection');
        if ($sniffer instanceof SqliteTableSniffer) {
            $this->assertTrue(true);
        } else {
            $this->expectException(Exception::class);
        }
        $this->FixtureManager->getSniffer('test_dummy_connection')->getDirtyTables();
    }

    /**
     * If a DB is not created, the sniffers should throw an exception
     */
    public function testGetAllTablesOnNonExistentDB()
    {
        $this->createNonExistentConnection();
        $sniffer = $this->FixtureManager->getSniffer('test_dummy_connection');
        if ($sniffer instanceof SqliteTableSniffer) {
            $this->assertTrue(true);
        } else {
            $this->expectException(Exception::class);
        }
        $this->FixtureManager->getSniffer('test_dummy_connection')->getAllTables();
    }

    public function testImplodeSpecial()
    {
        $array = ['foo', 'bar'];
        $glueBefore = 'ABC';
        $glueAfter = 'DEF';
        $expect = 'ABCfooDEFABCbarDEF';
        $this->assertSame($expect, $this->TableSniffer->implodeSpecial($glueBefore, $array, $glueAfter));
    }
}