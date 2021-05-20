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
namespace CakephpTestSuiteLight\Test\Traits;

use Cake\Datasource\ConnectionManager;

trait SnifferHelperTrait
{
    private function driverIs(string $driver): bool
    {
        return ConnectionManager::getConfig('test')['driver'] === 'Cake\Database\Driver\\' . $driver;
    }
}
