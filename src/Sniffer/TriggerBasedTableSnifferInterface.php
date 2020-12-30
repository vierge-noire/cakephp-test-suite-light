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


interface TriggerBasedTableSnifferInterface
{
    /**
     * The name of the table collecting dirty tables
     */
    const DIRTY_TABLE_COLLECTOR = 'test_suite_light_dirty_tables';

    const TRIGGER_PREFIX = 'dirty_table_spy_';

    const MAIN_MODE = 'MAIN_MODE';

    const TEMP_MODE = 'TEMP_MODE';

    /**
     * Create triggers on all tables listening to inserts
     * @return void
     */
    public function createTriggers(): void;

    /**
     * List all triggers
     * created by the interface
     * @return array
     */
    public function getTriggers(): array;

    /**
     * Drop all triggers
     * created by the interface
     * @return void
     */
    public function dropTriggers(): void;

    /**
     * Create the table gathering the dirty tables
     * @return void
     */
    public function createDirtyTableCollector(): void;

    /**
     * Mark all tables except phinxlogs as dirty
     * @return void
     */
    public function markAllTablesAsDirty(): void;

    /**
     * The dirty table collector is not temporary
     * @return void
     */
    public function activateMainMode(): void;

    /**
     * The dirty table collector is temporary
     * @return void
     */
    public function activateTempMode(): void;

    /**
     * Get the mode on which the sniffer is running
     * This defines if the collector table is
     * temporary or not
     * @return string
     */
    public function getMode(): string;
}