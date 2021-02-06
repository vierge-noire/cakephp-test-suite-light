<?php
declare(strict_types=1);

namespace CakephpTestSuiteLight\Test\TestCase;

use Cake\Datasource\ConnectionInterface;
use Cake\TestSuite\TestCase;
use CakephpTestSuiteLight\FixtureManager;
use CakephpTestSuiteLight\Sniffer\BaseTableSniffer;
use CakephpTestSuiteLight\TablesTruncation;
use CakephpTestSuiteLight\Test\Traits\TruncationHelperTrait;

/**
 * TablesTruncation test
 */
class FixtureManagerTruncationPoliciesTest extends TestCase
{
    use TruncationHelperTrait;

    public $mock;

    public function setUp(): void
    {
        parent::setUp();

        $this->resetPolicies();

        // We mock fixtureManager as we want to tweak at will provided connections and performs no truncations in real world
        $this->mock = $this
          ->getMockBuilder(FixtureManager::class)
          ->setMethods(['getActiveConnections', 'getSniffer'])
          ->getMock();

        $this->mock
          ->method('getActiveConnections')
          ->willReturn(['test', 'cloud1', 'cloud2']);

        $this->mock
          ->method('getSniffer')
          ->willReturn($this->createMock(BaseTableSniffer::class));
    }

    public function tearDown(): void
    {
        $this->mock = null;
        $this->resetPolicies();
        parent::tearDown();
    }


    public function testDefaultPolicy(): void
    {
        $this->assertEquals(['test', 'cloud1', 'cloud2'], $this->mock->truncateDirtyTables());
    }

    public function testDisableTruncationPolicy(): void
    {
        TablesTruncation::disable();
        $this->assertNull($this->mock->truncateDirtyTables());
    }

    public function testSkipAllTruncationPolicy(): void
    {
        TablesTruncation::skipAllTruncations();
        $this->assertEquals([], $this->mock->truncateDirtyTables());
    }

    public function testSkipAllTruncationPolicyOverridePerConnection(): void
    {
        TablesTruncation::skipAllTruncations();
        TablesTruncation::forceTruncation(['test']);
        $this->assertEquals(['test'], $this->mock->truncateDirtyTables());
    }

    public function testdoAllTruncationPolicyOverridePerConnection(): void
    {
        TablesTruncation::skipTruncation(['cloud1', 'cloud2']);
        $this->assertEquals(['test'], $this->mock->truncateDirtyTables());
    }

    public function testManuelTruncationRequest(): void
    {
        $this->assertEquals(['test'], $this->mock->truncateDirtyTables(['test']));
    }
}
