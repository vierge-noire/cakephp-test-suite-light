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

namespace TestCase\Annotations;

use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;
use CakephpTestSuiteLight\Annotations\FixturesProvider;
use CakephpTestSuiteLight\Test\Fixture\CitiesFixture;
use CakephpTestSuiteLight\Test\Fixture\CountriesFixture;
use TestApp\Model\Table\CountriesTable;

class FixtureProviderTest extends TestCase
{

    /**
     * @var CountriesTable
     */
    public $Countries;

    public $fixtures = [
        CountriesFixture::class,
        CitiesFixture::class,
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

    public function numberOfCountries()
    {
        return [[10]];
    }

    public function createCountries()
    {
        $numberOfCountries = $this->numberOfCountries()[0][0];
        for ($i = 0; $i < $numberOfCountries; $i++) {
            $data = ['name' => 'Country_' . $i];
            $countries = TableRegistry::getTableLocator()->get('Countries')->newEntity($data);
            TableRegistry::getTableLocator()->get('Countries')->saveOrFail($countries);
        }
    }

    /**
     * @FixturesProvider("createCountries")
     * @dataProvider numberOfCountries
     */
    public function testCreateManyCountriesWithFixtureProvider($n)
    {
        $this->assertSame($n + 1, $this->Countries->find()->count());
    }
}