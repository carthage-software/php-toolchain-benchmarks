<?php

declare(strict_types=1);

namespace CarthageSoftware\ToolChainBenchmarks\Support;

use Psl\Shell;

/**
 * Shell execution utilities for benchmark runners.
 *
 * All commands run with /tmp as working directory to prevent PHP-based tools
 * from discovering the benchmark suite's vendor/autoload.php and conflicting
 * with the workspace project's autoloader.
 */
final readonly class ShellHelper
{
    /**
     * Execute a shell command string, returning false on failure.
     */
    public static function exec(string $command): bool
    {
        try {
            Shell\execute('sh', ['-c', $command], '/tmp');
            return true;
        } catch (Shell\Exception\FailedExecutionException) {
            return false;
        }
    }
}
