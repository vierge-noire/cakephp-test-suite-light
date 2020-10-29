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
use TestApp\Model\Table\CountriesTable;

class CakeFixturesStringNotationTest extends TestCase
{
    /**
     * @var CountriesTable
     */
    public $Countries;

    public $fixtures = [
        'app.Countries',
        'app.Cities',
    ];

    public function setUp(): void
    {
        parent::setUp();

        $this->Countries = TableRegistry::getTableLocator()->get('Countries');
    }

    public function tearDown(): void
    {
        unset($this->Countries);
    }

    /**
     * With CakeFixtures only
     */
    public function testGetCountryFromCakeFixtures()
    {
        $countries = $this->Countries->find();
        $this->assertEquals(1, $countries->count());
    }

    /**
     * Create a Country the traditional way
     */
    public function testCreateCountry()
    {
        $country = $this->Countries->newEntity(['name' => 'Foo']);
        $this->Countries->saveOrFail($country);

        $countries = $this->Countries->find();
        $this->assertEquals(2, $countries->count());
    }
}
