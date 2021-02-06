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
     * @var StatisticTool
     */
    public $statisticTool;

    public function __construct(FixtureManager $manager, bool $withStatistics = false)
    {
        $this->_fixtureManager = $manager;
        $this->statisticTool   = new StatisticTool($manager, $withStatistics);
    }

    /**
     * Request sniffers initialization
     *
     * The tables should be created
     * in tests/bootstrap.php, either by migration or by running
     * the relevant Sql commands on the test DBs
     * See the Migrator tool provided here:
     * https://github.com/vierge-noire/cakephp-test-migrator
     * @param TestSuite $suite
     */
    public function startTestSuite(TestSuite $suite): void
    {
        $this->_fixtureManager->startSniffers();
    }

    /**
     * Cleanup before test starts
     * Truncates the tables that were used by the previous test before starting a new one
     * The truncation may be by-passed by setting in the test
     *
     * @param \PHPUnit\Framework\Test $test The test case
     * @return void
     */
    public function startTest(Test $test): void
    {
        $this->statisticTool->startsTestTime();

        // Apply local overrides to truncation policies
        $test->fixtureManager = $this->_fixtureManager; // needs this for skip and force traits
        if (method_exists($test, 'preserveOriginalPolicies')) {
            $test->preserveOriginalPolicies();
        }

        if (method_exists($test, 'overrideTruncationPolicies')) {
            $test->overrideTruncationPolicies($test);
        }

        // Apply truncation
        $this->_fixtureManager->truncateDirtyTables();

        $this->statisticTool->startsLoadingFixturesTime();

        // Load CakePHP fixtures
        parent::startTest($test);
        $this->statisticTool->stopsLoadingFixturesTime();

        // Run the seeds of your DB
//        $this->rollbackAndMigrateIfRequired();
    }

    /**
     * Collect the
     *
     * @param \PHPUnit\Framework\Test $test The test case
     * @param float                   $time current time
     * @return void
     */
    public function endTest(Test $test, float $time): void
    {
        if (method_exists($test, 'restoreOriginalPolicies')) {
            $test->restoreOriginalPolicies();
        }

        $this->statisticTool->stopsTestTime();
        $this->statisticTool->collectTestStatistics($test);
    }

    /**
     * The tables are not truncated at the end of the suite.
     * This way one can observe the content of the test DB
     * after a suite has been run.
     *
     * @param TestSuite $suite
     */
    public function endTestSuite(TestSuite $suite): void
    {
        $this->statisticTool->storeTestSuiteStatistics();
    }

    /**
     * Rollback the migrations defined in the config, and run them again
     * This can be useful if certain seed needs to be performed by migration
     * and should be recreated before each test
     *
     * @todo  TODO: the package aims at avoiding migrations
     * in its dependency. Let's think about a way to have this
     * done.
     */
//    public function rollbackAndMigrateIfRequired()
//    {
//        $configs = Configure::read('TestFixtureMarkedNonMigrated', []);
//
//        if (!empty($configs)) {
//            if (!isset($configs[0])) {
//                $configs = [$configs];
//            }
//            $migrations = new Migrations();
//            foreach ($configs as $config) {
//                $migrations->rollback($config);
//                $migrations->migrate($config);
//            }
//        }
//    }
}
