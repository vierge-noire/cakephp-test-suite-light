<?php


namespace CakephpTestSuiteLight\Sniffer;


interface TriggerBasedTableSnifferInterface
{
    /**
     * The name of the table collecting dirty tables
     */
    const DIRTY_TABLE_COLLECTOR = 'test_suite_light_dirty_tables';

    const TRIGGER_PREFIX = 'dirty_table_spy_';

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
     * The dirty table collector should never be dropped
     * This method helps removing it from a list of tables
     * @param array $tables
     * @return void
     */
    public function removeDirtyTableCollectorFromArray(array &$tables): void;

    /**
     * Create the table gathering the dirty tables
     * @return void
     */
    public function createDirtyTableCollector(): void;



}