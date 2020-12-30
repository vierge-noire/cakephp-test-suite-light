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
namespace CakephpTestSuiteLight\Sniffer;


use Cake\Database\Connection;
use CakephpTestSuiteLight\Sniffer\DriverTraits\PostgresSnifferTrait;

/**
 * Class PostgresTableSniffer
 * @deprecated Use trigger-based sniffers
 */
class PostgresTableSniffer extends BaseTableSniffer
{
    use PostgresSnifferTrait;

    /**
     * @inheritDoc
     */
    public function getDirtyTables(): array
    {
        return $this->fetchQuery("
            SELECT substr(sequencename, 1, length(sequencename) - 7)
            FROM pg_sequences
            WHERE last_value > 0;
        ");
    }

    /**
     * @inheritDoc
     */
    public function truncateDirtyTables()
    {
        $tables = $this->getDirtyTables();

        if (empty($tables)) {
            return;
        }

        $this->getConnection()->disableConstraints(function (Connection $connection) use ($tables) {
            $connection->transactional(function(Connection $connection) use ($tables) {
                $connection->execute(
                    'TRUNCATE "' . implode('", "', $tables) . '" RESTART IDENTITY CASCADE;'
                );
            });
        });
    }
}