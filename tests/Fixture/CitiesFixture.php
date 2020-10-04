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
namespace CakephpTestSuiteLight\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class CitiesFixture extends TestFixture
{

    public $fields = [
        'id' => ['type' => 'integer'],
        'name' => ['type' => 'string', 'length' => 255, 'null' => false],
        'country_id' => ['type' => 'integer'],
        'created' => 'datetime',
        'modified' => 'datetime',
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
                'name' => 'First City',
                'country_id' => 1,
                'created' => date('Y-m-d H:i:s'),
                'modified' => date('Y-m-d H:i:s'),
            ],
        ];
        parent::init();
    }
}