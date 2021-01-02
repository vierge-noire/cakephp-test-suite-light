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
namespace CakephpTestSuiteLight;

use Cake\TestSuite\TestCase;
use CakephpTestSuiteLight\Analyzer\RuntimeAnalyzer;
use CakephpTestSuiteLight\Analyzer\StaticFixtureAnalyzer;
use PHPUnit\Framework\Test;
use PHPUnit\Framework\TestSuite;

/**
 * This class has to be used along the fixture factories
 *
 * Class FixtureInjector
 * @package CakephpTestSuiteLight
 */
class FixtureInjector extends \Cake\TestSuite\Fixture\FixtureInjector
{
    /**
     * @var FixtureManager
     */
    public $_fixtureManager;

    /**
     * @var RuntimeAnalyzer
     */
    public $runtimeAnalyzer;

    /**
     * @var StaticFixtureAnalyzer
     */
    public $staticFixtureAnalyzer;

    public function __construct(
        FixtureManager $fixtureManager,
        bool $withStatistics = false,
        bool $withFixtureAnalyzer = false
    )
    {
        $this->_fixtureManager          = $fixtureManager;
        $this->runtimeAnalyzer          = new RuntimeAnalyzer($fixtureManager, $withStatistics);
        $this->staticFixtureAnalyzer    = new StaticFixtureAnalyzer($fixtureManager, $withFixtureAnalyzer);
    }

    /**
     * Nothing to do there. The tables should be created
     * in tests/bootstrap.php, either by migration or by running
     * the relevant Sql commands on the test DBs
     * See the Migrator tool provided here:
     * https://github.com/vierge-noire/cakephp-test-migrator
     * @param TestSuite $suite
     */
    public function startTestSuite(TestSuite $suite)
    {}

    /**
     * Cleanup before test starts
     * Truncates the tables that were used by the previous test before starting a new one
     * The truncation may be by-passed by setting in the test
     *
     * @param \PHPUnit\Framework\Test $test The test case
     * @return void
     */
    public function startTest(Test $test)
    {
        $this->truncateDirtyTables($test);
        $this->loadStaticFixtures($test);
    }

    /**
     * Collect data for the statistic tool
     *
     * @param \PHPUnit\Framework\Test $test The test case
     * @param float                   $time current time
     * @return void
     */
    public function endTest(Test $test, $time)
    {
        $this->runtimeAnalyzer->collectTestStatistics($test, $time);
    }

    /**
     * The tables are not truncated at the end of the suite.
     * This way one can observe the content of the test DB
     * after a suite has been run.
     *
     * @param TestSuite $suite
     */
    public function endTestSuite(TestSuite $suite)
    {
        $this->runtimeAnalyzer->storeResultsInCsv();
        $this->staticFixtureAnalyzer->storeResultsInCsv();
    }

    /**
     * If a test uses the SkipTablesTruncation trait, table truncation
     * does not occur between tests
     * @param Test $test
     * @return bool
     */
    public function skipTablesTruncation(Test $test): bool
    {
        return isset($test->skipTablesTruncation) ? $test->skipTablesTruncation : false;
    }

    /**
     * @return FixtureManager
     */
    public function getFixtureManager(): FixtureManager
    {
        return $this->_fixtureManager;
    }

    /**
     * @param Test $test
     */
    public function truncateDirtyTables(Test $test)
    {
        // Truncation can be skipped if no DB interaction are expected
        if (!$this->skipTablesTruncation($test)) {
            $this->getFixtureManager()->truncateDirtyTables();
        }
    }

    /**
     * @param Test $test
     * @return void
     */
    public function loadStaticFixtures(Test $test)
    {
        if (!$this->staticFixtureAnalyzer->handleTest($test)) {
            if ($test instanceof TestCase) {
                $this->getFixtureManager()->setFixtures((array)$test->fixtures);
            }
            parent::startTest($test);
        }
    }
}
