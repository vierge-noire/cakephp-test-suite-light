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
namespace TestApp\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;
use CakephpTestSuiteLight\Test\TestUtil;

class CitiesFixture extends TestFixture
{

    /**
     * @deprecated This attribute is not considered anymore
     * The schema is maintained by the migrations exclusively
     * @var array
     */
    public $fields = [
        'id' => ['type' => 'integer'],
        'name' => ['type' => 'string', 'length' => 255, 'null' => false],
        'country_id' => ['type' => 'integer'],
        'created' => 'datetime',
        'modified' => 'datetime',
        '_indexes' => [
            'country_id' => ['type' => 'index', 'columns' => ['country_id'], 'length' => []],
        ],
        '_constraints' => [
            'primary' => ['type' => 'primary', 'columns' => ['id']],
            'country_id' => [
                'type' => 'foreign',
                'references' => ['countries', 'id'],
                'update' => 'restrict',
                'delete' => 'restrict',
                'columns' => ['country_id'],
            ],
        ]
    ];

    public function init(): void
    {
        $this->records = [
            [
                'uuid_primary_key' => TestUtil::makeUuid(),
                'name' => 'First City',
                'country_id' => 1,
                'created' => date('Y-m-d H:i:s'),
                'modified' => date('Y-m-d H:i:s'),
            ],
        ];
        parent::init();
    }
}