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

use CakephpTestSuiteLight\AnnotationHandler;
use CakephpTestSuiteLight\Annotations\FixturesProvider;
use Cake\TestSuite\TestCase;

class AnnotationHandlerTest extends TestCase
{
    /**
     * @var AnnotationHandler
     */
    public $AnnotationHandler;

    public function setUp(): void
    {
        $this->AnnotationHandler = new AnnotationHandler();
    }

    public function helloWorld()
    {}

    /**
     * Expect "helloWorld"
     * @FixturesProvider("helloWorld")
     */
    public function testGetFixtureProvider()
    {
        $res = $this->AnnotationHandler->getFixtureProvider($this);
        $this->assertSame("helloWorld", $res);
    }

    /**
     * Expect an empty string
     */
    public function testGetFixtureProviderWithoutAnnotation()
    {
        $res = $this->AnnotationHandler->getFixtureProvider($this);
        $this->assertSame('', $res);
    }

    /**
     * Expect an empty string, no exception should be triggered
     * @whateverNotation
     */
    public function testGetFixtureProviderWithUnknownAnnotation()
    {
        $res = $this->AnnotationHandler->getFixtureProvider($this);
        $this->assertSame('', $res);
    }
}