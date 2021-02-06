<?php
declare(strict_types=1);

namespace CakephpTestSuiteLight;

trait ForceTablesTruncation
{
    use TablesTruncationTrait;

    public function overrideTruncationPolicies(\PHPUnit\Framework\Test $test): void
    {
        $this->forceTruncation('*');
    }
}
