<?php
declare(strict_types=1);

namespace CakephpTestSuiteLight;

class TablesTruncation
{
    /**
     * Checks if truncation is globally disabled through env.
     *
     * @return bool `true` if disabled
     */
    public static function isTruncationDisabled(): bool
    {
        return (bool)env('CTSL_DISABLE_TRUNCATION', false);
    }

    /**
     * Disable truncation. This is provided as convenience to enable truncation disabling
     * in test bootstrap
     *
     * @return void
     */
    public static function disable(): void
    {
        putenv('CTSL_DISABLE_TRUNCATION=1');
    }

    /**
     * Checks if default truncation behavior is prevented through env
     *
     * @return bool `true` if prevented
     */
    public static function isAutoTruncationPrevented(): bool
    {
        return (bool)env('CTSL_SKIP_ALL_TRUNCATIONS', false);
    }

    public static function doAllTruncations(): void
    {
        putenv('CTSL_SKIP_ALL_TRUNCATIONS');
    }

    public static function skipAllTruncations(): void
    {
        putenv('CTSL_SKIP_ALL_TRUNCATIONS=1');
    }

    public function forceTruncation(array $connections): void
    {
        putenv('CTSL_FORCE_TRUNCATION=' . implode(',', $connections));
    }

    public function resetForcedTruncations(): void
    {
        putenv('CTSL_FORCE_TRUNCATION');
    }

    public function skipTruncation(array $connections): void
    {
        putenv('CTSL_SKIP_TRUNCATION=' . implode(',', $connections));
    }

    public function resetSkippedTruncations(): void
    {
        putenv('CTSL_SKIP_TRUNCATION');
    }

    public static function getForcedConnections(): array
    {
        return self::_extractConnectionNames('CTSL_FORCE_TRUNCATION');
    }

    public static function getSkippedConnections(): array
    {
        return self::_extractConnectionNames('CTSL_SKIP_TRUNCATION');
    }

    public static function getConnectionsToTruncate(array $connections): array
    {
        // Auto truncation is prevented, returns only forced connections
        if (self::isAutoTruncationPrevented()) {
            return self::getForcedConnections();
        }

        // Auto truncation is enabled, returns connections that are not skipped
        $skipped = self::getSkippedConnections();

        return array_filter($connections, function ($connection) use ($skipped) {
            return !in_array($connection, $skipped);
        });
    }

    protected static function _extractConnectionNames(string $var): array
    {
        $val = env($var);

        if (empty($val)) {
            return [];
        }

        return explode(',', $val);
    }
}
