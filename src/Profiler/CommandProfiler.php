<?php

declare(strict_types=1);

namespace CarthageSoftware\ToolChainBenchmarks\Profiler;

use CarthageSoftware\ToolChainBenchmarks\Profiler\Internal\MemoryProfiler;
use CarthageSoftware\ToolChainBenchmarks\Profiler\Internal\PerformanceProfiler;
use CarthageSoftware\ToolChainBenchmarks\Profiler\Profile\CommandProfile;
use Psl\DateTime\Duration;

/**
 * Profiles a command by measuring its execution performance and memory usage.
 *
 * Given a command string and a total number of runs N:
 * - Runs the command N-1 times via Shell\execute to collect timing data.
 * - Runs the command 1 time via proc_open to measure peak memory (RSS polling).
 *   If memory reading is below 30MB, re-runs with /usr/bin/time -l for accuracy.
 *
 * Usage:
 *
 *     $profiler = new CommandProfiler(runs: 10, timeout: Duration\minutes(5));
 *     $profiler->setFailureCodes([137, 139, 255]);
 *     $profiler->setPrepareCommand('rm -rf /tmp/cache');
 *     $result = $profiler->profile('mago lint /path/to/project');
 *     if ($result instanceof CommandProfile) {
 *         // success — access $result->performance and $result->memory
 *     } else {
 *         // $result is ProfileFailure — check $result->reason
 *     }
 */
final class CommandProfiler
{
    /**
     * @var non-empty-list<int<0, 255>>
     */
    private array $failureCodes = [137, 255];

    /**
     * @var null|non-empty-string
     */
    private ?string $prepareCommand = null;

    /**
     * @param int<2, max>    $runs    Total number of runs. N-1 for performance, 1 for memory.
     * @param null|Duration  $timeout Timeout per individual run. Null for no timeout.
     */
    public function __construct(
        private readonly int $runs = 10,
        private readonly ?Duration $timeout = null,
    ) {}

    /**
     * Set which exit codes are treated as fatal failures.
     *
     * When a command exits with one of these codes, profiling stops immediately
     * and a {@see ProfileFailure} is returned.
     *
     * @param non-empty-list<int<0, 255>> $codes
     */
    public function setFailureCodes(array $codes): void
    {
        $this->failureCodes = $codes;
    }

    /**
     * Set a command to run before each individual benchmark run.
     *
     * Useful for clearing caches between runs (e.g. uncached analyzer benchmarks).
     *
     * @param null|non-empty-string $command
     */
    public function setPrepareCommand(?string $command): void
    {
        $this->prepareCommand = $command;
    }

    /**
     * Profile the given command.
     *
     * @param non-empty-string $command The shell command to profile.
     */
    public function profile(string $command): CommandProfile|ProfileFailure
    {
        // Step 1: Performance measurement (N-1 runs)
        $performance = PerformanceProfiler::measure(
            $command,
            $this->runs - 1,
            $this->timeout,
            $this->failureCodes,
            $this->prepareCommand,
        );
        if ($performance instanceof ProfileFailure) {
            return $performance;
        }

        // Step 2: Memory measurement (1 dedicated run)
        $memory = MemoryProfiler::measure($command, $this->timeout, $this->failureCodes, $this->prepareCommand);
        if ($memory instanceof ProfileFailure) {
            return $memory;
        }

        return new CommandProfile($command, $performance, $memory);
    }
}
