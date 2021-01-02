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


use Cake\TestSuite\TestCase;
use PHPUnit\Framework\Test;

class StaticFixtureAnalyzer extends BaseAnalyzer
{
    const REQUIRED = 'Static Fixtures Required';

    const NOT_REQUIRED = 'Static Fixtures Not Required';

    const NOT_SUPPORTED = 'Autofixture false not supported';

    /**
     * @var array
     */
    protected $tempResults = [];

    public function getHeader(): array
    {
        return [
            'Test Class',
            'Test Method',
            self::REQUIRED,
            self::NOT_REQUIRED,
        ];
    }

    /**
     * The tests get run multiple times while removing
     * at every run one and only one fixture.
     * In case of failures, the analyzer reports that
     * fixture as required in order to have the test running.
     * Returns false if the test is not going to be analyzed.
     * @param Test $test
     * @return bool
     */
    public function handleTest(Test $test): bool
    {
        if ($this->isNotActive()) {
            return false;
        }

        if (!($test instanceof TestCase)) {
            return false;
        }

        if ($test->autoFixtures === false) {
            $this->ignoreTest($test, self::NOT_SUPPORTED);
            return false;
        }

        $fixtures = $test->getFixtures();

        foreach ($fixtures as $i => $fixture) {
            $tempFixtures = $fixtures;
            unset($tempFixtures[$i]);
            $droppedFixture = $fixtures[$i];

            $this->prepareTest($test, $tempFixtures);

            try {
                $test->run();
                $fail = $test->hasFailed();
            } catch (\Exception $e) {
                $fail = true;
            }

            $this->storeResult($test, $fail, $droppedFixture);
        }

        return true;
    }

    /**
     * @param TestCase $test
     * @param array $fixtures
     */
    public function prepareTest(TestCase $test, array $fixtures)
    {
        $this->getFixtureManager()->truncateDirtyTables();
        $this->getFixtureManager()->unload($test);
        $this->getFixtureManager()->setFixtures($fixtures);
        $this->getFixtureManager()->load($test);
    }

    /**
     * @param TestCase $test
     * @param string $msg
     * @return void
     */
    public function ignoreTest(TestCase $test, string $msg)
    {
        $this->results[$test->getName(false)] = [
            get_class($test),
            $test->getName(false),
            $msg,
            null
        ];
    }

    /**
     * @param TestCase $test
     * @param bool $fail
     * @param string $droppedFixture
     * @return void
     */
    public function storeResult(TestCase $test, bool $fail, string $droppedFixture)
    {
        $testName = get_class($test) . '::' . $test->getName(false);
        $field = $fail ? self::REQUIRED : self::NOT_REQUIRED;

        if (isset($this->tempResults[$testName][$field])) {
            $this->tempResults[$testName][$field][] = $droppedFixture;
        } else {
            $this->tempResults[$testName][$field] = [$droppedFixture];
        }

        $this->results[$testName] = [
            get_class($test),
            $test->getName(false),
            $this->tempResults[$testName][self::REQUIRED] ?? null,
            $this->tempResults[$testName][self::NOT_REQUIRED] ?? null,
        ];
    }
}