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
namespace CakephpTestSuiteLight\Test\TestCase\Analyzer;

use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;
use TestApp\Model\Table\CitiesTable;
use TestApp\Model\Table\CountriesTable;
use TestApp\Test\Fixture\CitiesFixture;
use TestApp\Test\Fixture\CountriesFixture;

class StaticAnalyseCakeFixturesTest extends TestCase
{
    /**
     * @var CountriesTable
     */
    public $Countries;

    /**
     * @var CitiesTable
     */
    public $Cities;

    public $fixtures = [
        CountriesFixture::class,
        CitiesFixture::class,
    ];

    public function setUp(): void
    {
        parent::setUp();

        $this->Countries = TableRegistry::getTableLocator()->get('Countries');
        $this->Cities = TableRegistry::getTableLocator()->get('Cities');
    }

    public function tearDown(): void
    {
        unset($this->Countries);
        unset($this->Cities);
    }

    public function testFixtureTestRequiringCities()
    {
        $this->assertSame(
            1,
            $this->Cities->find()->count()
        );
    }

    public function testFixtureTestRequiringCountries()
    {
        $this->assertSame(
            1,
            $this->Countries->find()->count()
        );
    }

    public function testFixtureTestRequiringCountriesAndCities()
    {
        $this->testFixtureTestRequiringCities();
        $this->testFixtureTestRequiringCountries();
    }

    public function testFixtureTestRequiringNoCountriesAndNoCities()
    {
        $this->assertSame(true, true);
    }
}
