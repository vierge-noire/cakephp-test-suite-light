<?php
declare(strict_types=1);

namespace CakephpTestSuiteLight\Test\TestCase;

use Cake\TestSuite\TestCase;
use CakephpTestSuiteLight\FixtureManager;
use CakephpTestSuiteLight\TablesTruncation;
use CakephpTestSuiteLight\TablesTruncationTrait;
use CakephpTestSuiteLight\Test\Traits\TruncationHelperTrait;

/**
 * TablesTruncationTrait test
 */
class TablesTruncationTraitTest extends TestCase
{
    use TruncationHelperTrait;

    public function setUp()
    {
        parent::setUp();
        $this->resetPolicies();
    }

    public function tearDown()
    {
        $this->resetPolicies();
        parent::tearDown();
    }

    /**
     * Trait is supposed to store state when loaded for the first time and restore it when instance is destroyed     *
     * It's also testing most of trait functionnalities
     *
     * @return void
     */
    public function testStateStoreAndRestore()
    {
        $this->assertFalse(TablesTruncation::isAutoTruncationPrevented());

        // Setting up a bunch of policies
        TablesTruncation::skipAllTruncations();
        TablesTruncation::skipTruncation(['test'], ['test']);
        TablesTruncation::forceTruncation(['test'], ['test']);
        $trait = $this->getObjectForTrait(TablesTruncationTrait::class);

        $trait->preserveOriginalPolicies();
        $this->assertSame([
          'CTSL_DISABLE_TRUNCATION' => null,
          'CTSL_SKIP_ALL_TRUNCATIONS' => '1',
          'CTSL_SKIP_TRUNCATION' => 'test',
          'CTSL_FORCE_TRUNCATION' =>'test'
        ], $trait->getOriginalTruncationPolicies());

        // Truncation policies are tweaked by trait
        $trait->doAllTruncations();
        $trait->forceTruncation(false);
        $trait->skipTruncation(false);

        $this->assertFalse(TablesTruncation::isAutoTruncationPrevented());
        $this->assertEquals([], $trait->getForcedConnections());
        $this->assertEquals([], $trait->getSkippedConnections());

        // Policies should be restored when trait is destroyed
        $trait->restoreOriginalPolicies();
        $this->assertTrue(TablesTruncation::isAutoTruncationPrevented());
        $this->assertEquals(['test'], TablesTruncation::getForcedConnections());
        $this->assertEquals(['test'], TablesTruncation::getSkippedConnections());
    }

    public function testMassConnectionsAssignment()
    {
        $trait = $this->getObjectForTrait(TablesTruncationTrait::class);
        $trait->fixtureManager = $this->fixtureManager;
        $trait->preserveOriginalPolicies();

        $trait->forceTruncation('*');
        $trait->skipTruncation('*');
        $this->assertEquals(['test'], $trait->getForcedConnections());
        $this->assertEquals(['test'], $trait->getSkippedConnections());

        $trait->restoreOriginalPolicies();

        // Check state restoration on the reserved case
        $this->assertFalse(TablesTruncation::isAutoTruncationPrevented());
        $this->assertEquals([], TablesTruncation::getForcedConnections());
        $this->assertEquals([], TablesTruncation::getSkippedConnections());
    }

    public function testTruncateTables()
    {
        $fm = $this
          ->getMockBuilder(FixtureManager::class)
          ->setMethods(['truncateDirtyTables', 'getActiveConnections'])
          ->getMock();

        $fm
          ->expects($this->exactly(3))
          ->method('truncateDirtyTables')
          ->will($this->returnCallback(function ($connections) {
              return empty($connections) ? [] : $connections;
          }));

        $fm
          ->method('getActiveConnections')
          ->willReturn(['test', 'cloud']);

        $trait = $this->getObjectForTrait(TablesTruncationTrait::class);
        $trait->fixtureManager = $fm;

        $this->assertEquals([], $trait->truncateTables());
        $this->assertEquals(['test', 'cloud'], $trait->truncateTables('*'));
        $this->assertEquals(['test'], $trait->truncateTables('test'));
        $this->expectException(\PHPUnit\Framework\Exception::class);
        $trait->truncateTables('wrong');
    }
}
