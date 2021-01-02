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


use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;
use CakephpTestSuiteLight\Analyzer\StaticFixtureAnalyzer;
use CakephpTestSuiteLight\FixtureManager;
use TestApp\Model\Table\CitiesTable;
use TestApp\Model\Table\CountriesTable;
use TestApp\Test\Fixture\CountriesFixture;

class StaticFixtureAnalyzerTest extends TestCase
{
    /**
     * @var CountriesTable
     */
    public $Countries;

    /**
     * @var CitiesTable
     */
    public $Cities;

    /**
     * @var StaticFixtureAnalyzer
     */
    public $Analyzer;

    /**
     * @var bool Property to avoid infinite loops
     */
    public $stopHandler = false;

    public function setUp()
    {
        $this->Analyzer = new StaticFixtureAnalyzer(
            new FixtureManager(),
            true
        );

        $this->Countries = TableRegistry::getTableLocator()->get('Countries');
        $this->Cities = TableRegistry::getTableLocator()->get('Cities');
    }

    public function tearDown()
    {
        unset($this->Analyzer);
        unset($this->Countries);
        unset($this->Cities);
    }

    /**
     * Given a test with autofixture set to false
     * Then do not perform an analysis
     */
    public function testIgnoreAutoloadFixtureToFalse()
    {
        $testMock = $this->getMockBuilder(TestCase::class)->getMock();

        $testName = 'Foo';
        $testMock->method('getName')->willReturn($testName);

        $testMock->autoFixtures = false;
        $act = $this->Analyzer->handleTest($testMock);

        $this->assertSame(false, $act);
        $this->assertSame([
            'Foo' => [
                get_class($testMock),
                $testName,
                StaticFixtureAnalyzer::NOT_SUPPORTED,
                null,
            ]
        ], $this->Analyzer->getResults());
    }

    /**
     * Given a TestCase that is not CakePHP TestCase
     * Do not perform any analysis
     */
    public function testIgnoreNonCakephpTestCase()
    {
        $testMock = $this->getMockBuilder(\PHPUnit\Framework\TestCase::class)->getMock();

        $act = $this->Analyzer->handleTest($testMock);
        $this->assertSame(false, $act);
    }

    public function testIgnoreIfNotActive()
    {
        $testMock = $this->getMockBuilder(TestCase::class)->getMock();

        $this->Analyzer->setActive(false);

        $act = $this->Analyzer->handleTest($testMock);
        $this->assertSame(false, $act);
    }

    public function testWriteStatsInCsv()
    {
        $this->Analyzer->storeResultsInCsv();
        $this->assertFileExists(TMP . 'test_suite_light' . DS . 'StaticFixtureAnalyzer.csv');
    }

    /**
     * When two fixtures are required
     * And two fixtures are not required
     * All should be stored in the results as follows
     *
     * @covers StaticFixtureAnalyzer::storeResult()
     */
    public function testStoreResultWithTwoFixturesFailing()
    {
        $testMock = $this->getMockBuilder(TestCase::class)->getMock();
        $testName = 'Foo';
        $testMock->method('getName')->willReturn($testName);

        $this->Analyzer->storeResult($testMock, true, 'FixtureA');
        $this->Analyzer->storeResult($testMock, true, 'FixtureB');
        $this->Analyzer->storeResult($testMock, false, 'FixtureC');
        $this->Analyzer->storeResult($testMock, false, 'FixtureD');

        $expected = [
            get_class($testMock) . '::' . $testName => [
                get_class($testMock),
                $testName,
                [
                    'FixtureA',
                    'FixtureB',
                ],
                [
                    'FixtureC',
                    'FixtureD',
                ],
            ],
        ];

        $this->assertSame($expected, $this->Analyzer->getResults());
    }
}