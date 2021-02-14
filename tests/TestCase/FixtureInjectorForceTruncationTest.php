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
namespace CakephpTestSuiteLight\Test\TestCase;

use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;
use CakephpTestSuiteLight\FixtureInjector;
use CakephpTestSuiteLight\FixtureManager;
use CakephpTestSuiteLight\ForceTablesTruncation;
use CakephpTestSuiteLight\TablesTruncation;
use TestApp\Model\Table\CountriesTable;

class FixtureInjectorForceTruncationTest extends TestCase
{
    use ForceTablesTruncation;

    /**
     * @var FixtureManager
     */
    public $FixtureManager;

    /**
     * @var CountriesTable
     */
    public $Countries;

    /**
     * Makes sure that the country table starts empty
     */
    public static function setUpBeforeClass()
    {
        TableRegistry::getTableLocator()->get('Countries')->deleteAll(['1 = 1']);
        TablesTruncation::skipAllTruncations();
    }

    public function setUp()
    {
        $this->FixtureManager = new FixtureInjector(
            $this->createMock(FixtureManager::class)
        );

        $this->Countries = TableRegistry::getTableLocator()->get('Countries');
    }

    public function tearDown()
    {
        unset($this->Countries);
    }

    public static function tearDownAfterClass()
    {
        TablesTruncation::doAllTruncations();
    }

    public function iterator()
    {
        return [
            [1], [1], [1],
        ];
    }

    /**
     * @dataProvider iterator
     * @param int $expected
     */
    public function testTruncationSkipped(int $expected)
    {
        $this->assertTrue(TablesTruncation::isAutoTruncationPrevented());
        $country = $this->Countries->newEntity(['name' => 'foo']);
        $this->Countries->saveOrFail($country);
        $this->assertSame($expected, $this->Countries->find()->count());
    }
}
