<?php


namespace CakephpTestSuiteLight\Test\Traits;


use Cake\Datasource\ConnectionManager;

trait SnifferHelperTrait
{
    private function driverIs(string $driver): bool
    {
        return ConnectionManager::getConfig('test')['driver'] === 'Cake\Database\Driver\\' . $driver;
    }
}