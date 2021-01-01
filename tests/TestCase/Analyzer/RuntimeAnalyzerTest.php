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

namespace CakephpTestSuiteLight\Test\TestCase\Analyzer;

use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\TestCase;
use CakephpTestSuiteLight\Analyzer\RuntimeAnalyzer;
use CakephpTestSuiteLight\FixtureManager;
use TestApp\Test\Fixture\CitiesFixture;
use TestApp\Test\Fixture\CountriesFixture;

class RuntimeAnalyzerTest extends TestCase
{
    /**
     * @var RuntimeAnalyzer
     */
    public $analyzer;

    public $fixtures = [
        // The order here is important
        CountriesFixture::class,
        CitiesFixture::class,
    ];

    public $autoFixtures = false;

    public function setUp(): void
    {
        $this->analyzer = new RuntimeAnalyzer(
            new FixtureManager(),
            true
        );
    }

    public function tearDown(): void
    {
        unset($this->analyzer);
    }

    /**
     * Given 2 tables are created and the process time is 0.129s
     * When the fixture manager collects dirty tables
     * When the statistics get collected
     * Then the statistics should be coherent
     */
    public function testCollectTestStatistics()
    {
        // Arrange
        $this->loadFixtures();
        $time = 0.1239999;
        $this->analyzer->collectTestStatistics($this, $time);
        $db = ConnectionManager::get('test')->config()['database'];

        // Act
        $stats = $this->analyzer->getResults();

        // Assert
        $this->assertSame(1, count($stats));
        $stats = $stats[0];

        $this->assertSame(0.124, $stats[0]);
        $this->assertSame(self::class, $stats[1]);
        $this->assertSame(__FUNCTION__, $stats[2]);
        $this->assertSame(2, $stats[3]);
        $this->assertSame(["$db.cities", "$db.countries"], $stats[4]);
    }

    public function testWriteStatsInCsv()
    {
        $this->analyzer->storeResultsInCsv();
        $this->assertFileExists(TMP . 'test_suite_light' . DS . 'RuntimeAnalyzer.csv');
    }
}