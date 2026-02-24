<?php

declare(strict_types=1);

namespace CarthageSoftware\ToolChainBenchmarks\Profiler\Internal;

use Psl\Shell;

/**
 * Executes a prepare command before benchmark runs.
 *
 * Used by both {@see PerformanceProfiler} and {@see MemoryProfiler} to run
 * cache-clearing or other setup commands before each benchmark iteration.
 *
 * @internal
 */
final readonly class PrepareRunner
{
    /**
     * Run the prepare command if set. Failures are silently ignored.
     *
     * @param null|non-empty-string $command
     */
    public static function run(?string $command): void
    {
        if ($command === null) {
            return;
        }

        try {
            Shell\execute('sh', ['-c', $command], '/tmp');
        } catch (Shell\Exception\FailedExecutionException) { // @mago-expect lint:no-empty-catch-clause
            // Prepare command failures are non-fatal (e.g. cache dir doesn't exist yet)
        }
    }
}
