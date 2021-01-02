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

namespace CakephpTestSuiteLight\Analyzer;

use Cake\Datasource\ConnectionManager;
use CakephpTestSuiteLight\Sniffer\TriggerBasedTableSnifferInterface;
use PHPUnit\Framework\Test;

class RuntimeAnalyzer extends BaseAnalyzer
{
    /**
     * @var array
     */
    private $dirtyTables = [];

    /**
     * Go through the manager connections and collect dirty tables
     * @return void
     */
    public function collectDirtyTables()
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
    public function collectTestStatistics(Test $test, float $time)
    {
        if ($this->isNotActive()) {
            return;
        }

        $this->collectDirtyTables();

        $dirtyTables = $this->castDirtyTables();
        $testName = method_exists($test, 'getName') ? $test->getName() : 'Test name undefined';

        $this->results[] = [
            round($time * 1000) / 1000,             // Time in seconds
            get_class($test),                           // Test Class name
            $testName,                           // Test method name
            count($dirtyTables),                        // Number of dirty tables
            $dirtyTables,                         // Dirty tables
        ];
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

    public function getHeader(): array
    {
        return [
            'Time (seconds)',
            'Test Class',
            'Test Method',
            '# Dirty Tables',
            'Dirty Tables',
        ];
    }
}