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
namespace CakephpTestSuiteLight\Test\DropTablesTestCase;


use Cake\Database\Driver\Sqlite;
use Cake\Datasource\ConnectionManager;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;
use CakephpTestSuiteLight\FixtureManager;
use CakephpTestSuiteLight\Sniffer\BaseTableSniffer;
use CakephpTestSuiteLight\Test\Fixture\CitiesFixture;
use CakephpTestSuiteLight\Test\Fixture\CountriesFixture;
use TestApp\Model\Entity\City;
use TestApp\Model\Entity\Country;
use TestApp\Model\Table\CitiesTable;
use TestApp\Model\Table\CountriesTable;

class TableSnifferDropTablesTest extends TestCase
{
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
     * @var FixtureManager
     */
    public $FixtureManager;

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
        $this->FixtureManager = new FixtureManager();
        $this->TableSniffer = $this->FixtureManager->getSniffer('test');
        $this->Countries = TableRegistry::getTableLocator()->get('Countries');
        $this->Cities = TableRegistry::getTableLocator()->get('Cities');
    }

    public function tearDown(): void
    {
        $this->FixtureManager->unload($this);
        $this->FixtureManager->load($this);

        unset($this->TableSniffer);
        unset($this->FixtureManager);
        unset($this->Countries);
        unset($this->Cities);
        ConnectionManager::drop('test_dummy_connection');

        parent::tearDown();
    }

    /**
     * After dropping all tables, only the package migration table should remain
     * This should never be dropped
     */
    public function testGetAllTablesAfterDroppingAll()
    {
        $this->assertSame(
            1,
            $this->Countries->find()->count()
        );
        $this->assertSame(
            1,
            $this->Cities->find()->count()
        );

        $this->FixtureManager->dropTables('test');
        $this->assertSame([], $this->TableSniffer->getAllTables());

        $this->FixtureManager->unload($this);
    }
}