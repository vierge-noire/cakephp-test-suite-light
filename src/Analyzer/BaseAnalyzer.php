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


use CakephpTestSuiteLight\FixtureManager;

abstract class BaseAnalyzer
{
    /**
     * @var bool
     */
    protected $isActive = false;

    /**
     * @var FixtureManager
     */
    protected $fixtureManager;

    /**
     * @var array
     */
    protected $results = [];

    /**
     * @var string
     */
    protected $fileName;

    const TABLE_SEPARATOR = ', ';

    /**
     * StatisticTool constructor.
     *
     * @param FixtureManager $fixtureManager
     * @param bool          $isActive
     */
    public function __construct(
        FixtureManager $fixtureManager,
        $isActive = false
    )
    {
        $this->fixtureManager = $fixtureManager;
        $this->setActive($isActive);
    }

    /**
     * Header of the CSV file generated
     *
     * @return array
     */
    abstract public function getHeader(): array;

    /**
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->isActive === true;
    }

    /**
     * @return bool
     */
    public function isNotActive(): bool
    {
        return !$this->isActive();
    }

    /**
     * @return string
     */
    public function getFileName(): string
    {
        $dirName = TMP . 'test_suite_light';
        if (!file_exists($dirName)) {
            mkdir($dirName, 0777, true);
        }

        $fileName = substr(get_class($this), strrpos(get_class($this), '\\') + 1);

        return $dirName . DS . $fileName . '.csv';
    }

    /**
     * @return array
     */
    public function getResults(): array
    {
        return $this->results;
    }

    /**
     * @return FixtureManager
     */
    public function getFixtureManager(): FixtureManager
    {
        return $this->fixtureManager;
    }

    /**
     * Write Stats in a CSV file
     * @return void
     */
    public function storeResultsInCsv(): void
    {
        if ($this->isNotActive()) {
            return;
        }

        $resultFile = fopen($this->getFileName(), 'w');

        if (!$resultFile) {
            return;
        }

        fputcsv($resultFile, $this->getHeader());

        foreach ($this->getResults() as $stat) {
            foreach ($stat as $i => $s) {
                if (is_array($s)) {
                    $stat[$i] = implode(self::TABLE_SEPARATOR, $s);
                }
            }
            fputcsv($resultFile, $stat);
        }

        fclose($resultFile);
    }

    /**
     * @param bool $isActive
     */
    public function setActive(bool $isActive): void
    {
        $this->isActive = $isActive;
    }
}