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
use Cake\Database\Exception;

/**
 * Class SqliteTableSniffer
 * @deprecated Use trigger-based sniffers
 */
class SqliteTableSniffer extends BaseTableSniffer
{
    /**
     * @inheritDoc
     */
    public function getDirtyTables(): array
    {
        try {
            $result = $this->fetchQuery("
             SELECT name FROM sqlite_sequence WHERE name NOT LIKE '%phinxlog';
         ");
        } catch (Exception $exception) {
            $result = $this->getAllTables();
        }
        return $result;
    }

    /**
     * @inheritDoc
     */
    public function truncateDirtyTables(): void
    {
        $tables = $this->getDirtyTables();

        if (empty($tables)) {
            return;
        }

        $this->getConnection()->disableConstraints(function (Connection $connection) use ($tables) {
            $connection->transactional(function(Connection $connection) use ($tables) {
                foreach ($tables as $table) {
                    $connection
                        ->newQuery()
                        ->delete($table)
                        ->execute();
                    $connection
                        ->newQuery()
                        ->delete('sqlite_sequence')
                        ->where(['name' => $table])
                        ->execute();
                }
            });
        });
    }

    /**
     * @inheritDoc
     */
    public function fetchAllTables(): array
    {
        return $this->fetchQuery("
             SELECT name FROM sqlite_master WHERE type='table' AND name != 'sqlite_sequence';
        ");
    }

    /**
     * @inheritDoc
     */
    public function dropTables(array $tables): void
    {
        if (empty($tables)) {
            return;
        }

        $this->getConnection()->disableConstraints(function (Connection $connection) use ($tables) {
            $connection->transactional(function(Connection $connection) use ($tables) {
                foreach ($tables as $table) {
                    $connection->execute("DROP TABLE IF EXISTS $table;");
                }
            });
        });
    }
}