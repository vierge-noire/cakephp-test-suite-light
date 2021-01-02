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


use Cake\TestSuite\TestCase;
use CakephpTestSuiteLight\FixtureManager;
use CakephpTestSuiteLight\Sniffer\BaseTableSniffer;
use CakephpTestSuiteLight\Sniffer\MysqlTriggerBasedTableSniffer;
use CakephpTestSuiteLight\Sniffer\TriggerBasedTableSnifferInterface;
use CakephpTestSuiteLight\Test\Traits\ArrayComparerTrait;
use Migrations\Migrations;

class TableSnifferWithMigrationTest extends TestCase
{
    use ArrayComparerTrait;

    /**
     * @var Migrations
     */
    public $migrations;

    /**
     * @var BaseTableSniffer
     */
    public $TableSniffer;


    public function setUp()
    {
        $fixtureManager = new FixtureManager();
        $this->TableSniffer = $fixtureManager->getSniffer('test');

        $config = [
            'connection' => 'test',
            'source' => 'TestMigrations',
        ];

        $this->migrations = new Migrations($config);
        $this->migrations->migrate($config);
    }

    public function tearDown()
    {
        unset($this->TableSniffer);

        $this->migrations->rollback();
        $this->migrations->rollback();
    }

    protected function countProducts(): int
    {
        return (int) $nProducts = $this->TableSniffer->fetchQuery(
            'SELECT COUNT(*) FROM products'
        )[0];
    }

    /**
     * Find dirty tables
     * Since the table products was created
     * after the setup of the sniffer triggers,
     * it is not marked as dirty
     */
    public function testPopulateWithMigrationsWithoutSetup()
    {
        $tables = $this->TableSniffer->fetchAllTables();
        $this->assertTrue(in_array('products', $tables));

        if ($this->TableSniffer->implementsTriggers()) {
            $this->assertSame([], $this->TableSniffer->getDirtyTables());
        } else {
            $this->assertSame(['products'], $this->TableSniffer->getDirtyTables());
        }
    }

    public function testPopulateWithMigrationsWithSetup()
    {
        $tables = $this->TableSniffer->fetchAllTables();
        $this->assertTrue(in_array('products', $tables));

        // Rollback the table products population migration
        $this->migrations->rollback();

        $expected = [
            'dirty_table_spy_countries',
            'dirty_table_spy_cities',
        ];
        if (!($this->TableSniffer instanceof MysqlTriggerBasedTableSniffer)) {
            $expected[] = 'dirty_table_spy_' . TriggerBasedTableSnifferInterface::DIRTY_TABLE_COLLECTOR;
        }

        if ($this->TableSniffer->implementsTriggers()) {
            $this->assertArraysHaveSameContent($expected, $this->TableSniffer->getTriggers());
        }

        // Reset the triggers
        $this->TableSniffer->setup();

        if ($this->TableSniffer->implementsTriggers()) {
            $expected[] = 'dirty_table_spy_products';
            $this->assertArraysHaveSameContent( $expected, $this->TableSniffer->getTriggers());
        }

        $nProducts = $this->countProducts();

        // Populate the products table
        $this->migrations->migrate();

        if ($this->TableSniffer->implementsTriggers()) {
            $this->assertArraysHaveSameContent($expected, $this->TableSniffer->getTriggers());
        }

        // Assert that a product was created
        $this->assertSame($nProducts + 1, $this->countProducts());

        // Assert that the products table is marked dirty
        $this->assertContains('products', $this->TableSniffer->getDirtyTables());
    }
}