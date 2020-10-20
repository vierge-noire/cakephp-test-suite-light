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

namespace TestCase;

use CakephpTestSuiteLight\FixtureInjector;
use CakephpTestSuiteLight\FixtureManager;
use PHPUnit\Framework\TestCase;

class FixtureInjectorTest extends TestCase
{
    /**
     * @var FixtureInjector
     */
    public $FixtureInjector;

    public function setUp()
    {
        $this->FixtureInjector = new FixtureInjector(new FixtureManager(), true);
    }

    /**
     * Start test should work for PHPUnit TestCase
     */
    public function testStartTestWithPhpunitTestCase()
    {
        $test = $this->createMock(TestCase::class);
        $this->FixtureInjector->startTest($test);
        $this->assertTrue(true);
    }

    /**
     * Start test should work for CakePHP TestCase
     */
    public function testStartTestWithCakeTestCase()
    {
        $test = $this->createMock(\Cake\TestSuite\TestCase::class);
        $this->FixtureInjector->startTest($test);
        $this->assertTrue(true);
    }

    /**
     * End test should work for PHPUnit TestCase
     */
    public function testEndTestWithPhpunitTestCase()
    {
        $test = $this->createMock(TestCase::class);
        $this->FixtureInjector->endTest($test, 0);
        $this->assertTrue(true);
    }

    /**
     * End test should work for CakePHP TestCase
     */
    public function testEndTestWithCakeTestCase()
    {
        $test = $this->createMock(\Cake\TestSuite\TestCase::class);
        $this->FixtureInjector->endTest($test, 0);
        $this->assertTrue(true);
    }
}