<?php
declare(strict_types=1);

namespace CakephpTestSuiteLight;

use CakephpTestSuiteLight\TablesTruncation;

trait TablesTruncationTrait
{
    /**
     * Policies var names
     * @var string[]
     */
    protected static $_policies = [
        'CTSL_DISABLE_TRUNCATION',
        'CTSL_SKIP_ALL_TRUNCATIONS',
        'CTSL_SKIP_TRUNCATION',
        'CTSL_FORCE_TRUNCATION',
    ];

    /**
     * Stores the initial state before test is run
     *
     * @var array
     */
    protected $_state = [];

    public function getOriginalTruncationPolicies(): array
    {
        return $this->_state;
    }

    public function getCurrentTruncationPolicies(): array
    {
        $policies = [];

        foreach (self::$_policies as $policy) {
            $policies[$policy] = env($policy);
        }

        return $policies;
    }

    /**
     * Fetch all vars in environment and store it in a static var
     *
     * @return void
     */
    public function preserveOriginalPolicies(): void
    {
        $this->_state = $this->getCurrentTruncationPolicies();
    }

    public function overrideTruncationPolicies(\PHPUnit\Framework\Test $test): void
    {
    }

    /**
     * Restore current state in environment
     *
     * @return void
     */
    public function restoreOriginalPolicies(): void
    {
        foreach ($this->_state as $var => $val) {
            putenv($val === null ? $var : "$var=$val");
        }
    }

    public function refreshState(): void
    {
        $this->_storeInitialState();
    }

    /**
     * Setup environment to enable automatic truncations
     *
     * @return void
     */
    public function doAllTruncations(): void
    {
        TablesTruncation::doAllTruncations();
    }

    /**
     * Set up environment to disable automatic truncations
     *
     * @return void
     */
    public function skipAllTruncations(): void
    {
        TablesTruncation::skipAllTruncations();
    }

    public function getForcedConnections(): array
    {
        return TablesTruncation::getForcedConnections();
    }

    /**
     * Defines connections to be truncated or reset setting. All available connections
     * can be quicly assigned with '*'
     *
     * @param  bool|string $connectionOrReset Connection or reset
     * @param  string ...$connections       Additional connections
     * @return void
     */
    public function forceTruncation($connectionOrReset, string ...$connections): void
    {
        if ($connectionOrReset === false) {
            TablesTruncation::resetForcedTruncations();
            return;
        }

        $availables = $this->_getFixtureManager()->getActiveConnections();

        if ($connectionOrReset === '*') {
            TablesTruncation::forceTruncation($availables);
            return;
        }

        array_unshift($connections, $connectionOrReset);
        $this->_checkConnectionsName($connections, $availables);
        TablesTruncation::enableTruncation($connections);
    }

    public function getSkippedConnections(): array
    {
        return TablesTruncation::getSkippedConnections();
    }

    /**
     * Defines connections to be skipped or reset setting. All available connections
     * can be quickly assigned with '*'
     *
     * @param bool|string $connectionOrReset Connection or reset
     * @param string ...$connections       Additional connections
     * @return void
     */
    public function skipTruncation($connectionOrReset, string ...$connections): void
    {
        if ($connectionOrReset === false) {
            TablesTruncation::resetSkippedTruncations();
            return;
        }

        $availables = $this->_getFixtureManager()->getActiveConnections();

        if ($connectionOrReset === '*') {
            TablesTruncation::skipTruncation($availables);
            return;
        }

        array_unshift($connections, $connectionOrReset);
        $this->_checkConnectionsName($connections, $availables);
        TablesTruncation::skipTruncation($connections);
    }

    /**
     * Manually truncates connection(s).
     *
     * This has the highest priority against any policy if providing connection name(s)
     * If no arguments are provided, the truncations are done accordingly to the current policies.
     *
     * @param string|null $connection  Connection to truncate
     * @param string ...$connections Additionnal connections to truncate
     * @return array|null Null if truncation is disabled or an array with the list of truncated connections
     */
    public function truncateTables(?string $connection = null, string ...$connections): array
    {
        if (empty($connection)) {
            return $this->_getFixtureManager()->truncateDirtyTables();
        }

        $availables = $this->_getFixtureManager()->getActiveConnections();

        if ($connection === '*') {
            return $this->_getFixtureManager()->truncateDirtyTables($availables);
        }

        array_unshift($connections, $connection);
        $this->_checkConnectionsName($connections, $availables);
        return $this->_getFixtureManager()->truncateDirtyTables($connections);
    }

    /**
     * Checks connection names against available ones
     *
     * @param array $connections Connections to check
     * @param array $available   Available connections
     * @return void
     * @throws \PhpUnit\Framework\Exception
     */
    protected function _checkConnectionsName(array $connections, array $available): void
    {
        foreach ($connections as $connection) {
            if (!in_array($connection, $available)) {
                $msg = "Test suite light error : Unrecognized connection {$connection}";
                throw new \PHPUnit\Framework\Exception($msg);
            }
        }
    }

    /**
     * Returns the fixture manager
     *
     * @return FixtureManager Fixture manager
     * @throws \RuntimeException
     */
    protected function _getFixtureManager(): FixtureManager
    {
        if ($this->fixtureManager === null) {
            throw new \RuntimeException('Test suite light error : No fixture manager available');
        }

        return $this->fixtureManager;
    }
}
