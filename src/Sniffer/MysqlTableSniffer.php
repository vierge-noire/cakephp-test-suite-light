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

class MysqlTableSniffer extends BaseTableSniffer
{
    /**
     * @inheritDoc
     */
    public function getDirtyTables(): array
    {
        return $this->fetchQuery("
            SELECT table_name
            FROM INFORMATION_SCHEMA.TABLES
            WHERE
                TABLE_SCHEMA = DATABASE()
                AND table_name NOT LIKE '%phinxlog'
                AND TABLE_ROWS > 0;
        ");
    }

    /**
     * @inheritDoc
     */
    public function truncateTables(array $tables)
    {
        if (empty($tables)) {
            return;
        }

        $this->getConnection()->disableConstraints(function (Connection $connection) use ($tables) {
            $connection->transactional(function(Connection $connection) use ($tables) {
                $connection->execute(
                    $this->implodeSpecial(
                        "TRUNCATE TABLE `",
                        $tables,
                        "`;"
                    )
                );
            });
        });
    }

    /**
     * @inheritDoc
     */
    public function getAllTables(): array
    {
        return $this->fetchQuery("
            SELECT table_name
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = DATABASE();
        ");
    }

    /**
     * @inheritDoc
     */
    public function dropTables(array $tables)
    {
        if (empty($tables)) {
            return;
        }

        $this->getConnection()->disableConstraints(function (Connection $connection) use ($tables) {
            $connection->transactional(function(Connection $connection) use ($tables) {
                $connection->execute(
                    $this->implodeSpecial(
                        'DROP TABLE IF EXISTS `',
                        $tables,
                        '`;'
                    )
                );
            });
        });
    }
}
