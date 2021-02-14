<?php
declare(strict_types=1);

namespace CakephpTestSuiteLight\Test\Traits;

use CakephpTestSuiteLight\FixtureManager;
use CakephpTestSuiteLight\TablesTruncation;

/**
 * Helper trait to ^provide a quick too to reset all truncation settings
 */
trait TruncationHelperTrait
{
    /**
     * Reset all policies to standard behavior
     */
    public function resetPolicies()
    {
        putenv('CTSL_DISABLE_TRUNCATION');
        TablesTruncation::doAllTruncations();
        TablesTruncation::resetForcedTruncations();
        TablesTruncation::resetSkippedTruncations();
    }
}
