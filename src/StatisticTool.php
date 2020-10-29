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
use CakephpTestSuiteLight\Sniffer\TriggerBasedTableSnifferInterface;
use PHPUnit\Framework\Test;

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
     * @var array
     */
    private $dirtyTables = [];

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

    /**
     * StatisticTool constructor.
     *
     * @param FixtureManager $manager
     * @param bool          $isActivated
     */
    public function __construct(
        FixtureManager $manager,
        $isActivated = false
    )
    {
        $this->fixtureManager = $manager;
        $this->isActivated = $isActivated;
        $this->setFileName();
    }

    /**
     * @return bool
     */
    public function isNotActivated(): bool
    {
        return $this->isActivated !== true;
    }

    /**
     * Go through the manager connections and collect dirty tables
     * @return void
     */
    public function collectDirtyTables(): void
    {
        foreach ($this->getFixtureManager()->getActiveConnections() as $connectionName) {
            $this->dirtyTables[$connectionName] = $this->getFixtureManager()->getSniffer($connectionName)->getDirtyTables();
        }
    }

    /**
     * @return array
     */
    public function getDirtyTables(): array
    {
        return $this->dirtyTables;
    }

    /**
     * @param Test  $test
     * @param float $time
     * @return void
     */
    public function collectTestStatistics(Test $test, float $time): void
    {
        if ($this->isNotActivated()) {
            return;
        }

        $this->collectDirtyTables();

        $dirtyTables = $this->castDirtyTables();
        $testName = method_exists($test, 'getName') ? $test->getName() : 'Test name undefined';

        $this->statistics[] = [
            round($time * 1000) / 1000,             // Time in seconds
            get_class($test),                           // Test Class name
            $testName,                           // Test method name
            count($dirtyTables),                        // Number of dirty tables
            implode(', ', $dirtyTables),           // Dirty tables
        ];
    }

    /**
     * Write the collected data in a csv data
     * @return void
     */
    public function storeTestSuiteStatistics(): void
    {
        $this->writeStatsInCsv();
    }

    /**
     * Extract all dirty tables prefixed with their DB
     * Sort them
     * @return array
     */
    private function castDirtyTables(): array
    {
        $tables = [];
        foreach ($this->getDirtyTables() as $connection => $dirtyTables) {
            $db = ConnectionManager::get($connection)->config()['database'];
            foreach ($dirtyTables as $i => $dirtyTable) {
                if ($dirtyTable !== TriggerBasedTableSnifferInterface::DIRTY_TABLE_COLLECTOR) {
                    $dirtyTables[$i] = "$db.$dirtyTable";
                } else {
                    unset($dirtyTables[$i]);
                }
            }
            $tables += $dirtyTables;
        }
        $tables = array_unique($tables);
        sort($tables);

        return $tables;
    }

    /**
     * Write Stats in a CSV file
     * @return void
     */
    public function writeStatsInCsv(): void
    {
        if ($this->isNotActivated()) {
            return;
        }

        $statFile = fopen($this->getFileName(), 'w');

        if (!$statFile) {
            return;
        }

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
    public function setFileName(): void
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