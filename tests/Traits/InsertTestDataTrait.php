<?php


namespace CakephpTestSuiteLight\Test\Traits;


use Cake\Datasource\EntityInterface;
use Cake\ORM\TableRegistry;
use CakephpTestSuiteLight\Test\TestUtil;

trait InsertTestDataTrait
{
    private function createCountry(): EntityInterface
    {
        $Countries = TableRegistry::getTableLocator()->get('Countries');
        $country = $Countries->newEntity([
            'name' => 'Foo',
        ]);
        return $Countries->saveOrFail($country);
    }

    private function createCity(): EntityInterface
    {
        $Cities = TableRegistry::getTableLocator()->get('Cities');
        $city = $Cities->newEntity([
            'uuid_primary_key' => TestUtil::makeUuid(),
            'id_primary_key' => rand(1, 99999999),
            'name' => 'Foo',
            'country_id' => $this->createCountry()->id
        ]);
        return $Cities->saveOrFail($city);
    }
}
