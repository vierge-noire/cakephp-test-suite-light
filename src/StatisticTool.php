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

use Cake\Datasource\ConnectionManager;
use PHPUnit\Framework\Test;
use PHPUnit\Framework\TestSuite;

class StatisticTool
{
    /**
     * @var FixtureManager
     */
    private $fixtureManager;
    /**
     * @var array
     */
    private $statistics = [];

    /**
     * @var string
     */
    private $fileName;

    /**
     * @var bool
     */
    private $isActivated;

    /**
     * @var float
     */
    public $time;

    public function __construct(
        FixtureManager $manager,
        $isActivated = false
    )
    {
        $this->fixtureManager = $manager;
        $this->isActivated = $isActivated;
        $this->setFileName();
    }

    public function isNotActivated(): bool
    {
        return $this->isActivated !== true;
    }

    /**
     * @param Test  $test
     * @param float $time
     */
    public function collectTestStatistics(Test $test, float $time)
    {
        if ($this->isNotActivated()) {
            return;
        }

        $dirytTables = $this->castDirtyTables();

        $this->statistics[] = [
            round($time * 1000) / 1000,             // Time in seconds
            get_class($test),                           // Test Class name
            $test->getName(),                           // Test method name
            count($dirytTables),                        // Number of dirty tables
            implode(', ', $dirytTables),           // Dirty tables
        ];
    }

    public function storeTestSuiteStatistics(TestSuite $suite)
    {
        $this->writeStatsInCsv();
    }

    private function castDirtyTables(): array
    {
        $tables = [];
        foreach ($this->getFixtureManager()->getDirtyTables() as $connection => $dirtyTables) {
            $db = ConnectionManager::get($connection)->config()['database'];
            foreach ($dirtyTables as $i => $dirtyTable) {
                $dirtyTables[$i] = "$db.$dirtyTable";
            }
            $tables += $dirtyTables;
        }
        $tables = array_unique($tables);
        sort($tables);

        return $tables;
    }

    public function writeStatsInCsv()
    {
        if ($this->isNotActivated()) {
            return;
        }

        $statFile = fopen($this->getFileName(), 'w');

        fputcsv($statFile, [
            'Time (seconds)',
            'Test Class',
            'Test Method',
            '# Dirty Tables',
            'Dirty Tables',
        ]);

        foreach ($this->statistics as $stat) {
            fputcsv($statFile, $stat);
        }

        fclose($statFile);
    }

    /**
     * @return string
     */
    public function getFileName(): string
    {
        return $this->fileName;
    }

    /**
     *
     */
    public function setFileName()
    {
        if ($this->isNotActivated()) {
            return;
        }

        $dirName = TMP . 'test_suite_light';
        if (!file_exists($dirName)) {
            mkdir($dirName, 0777, true);
        }

        $this->fileName = $dirName . DS . 'test_suite_statistics.csv';
    }

    /**
     * @return array
     */
    public function getStatistics(): array
    {
        return $this->statistics;
    }

    /**
     * @return FixtureManager
     */
    public function getFixtureManager(): FixtureManager
    {
        return $this->fixtureManager;
    }
}