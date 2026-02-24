<?php

declare(strict_types=1);

namespace CarthageSoftware\ToolChainBenchmarks\Profiler\Profile;

/**
 * Complete profiling result for a single command: performance timings + peak memory.
 */
final readonly class CommandProfile
{
    /**
     * @param non-empty-string   $command     The profiled command.
     * @param PerformanceProfile $performance Timing results from N-1 runs.
     * @param MemoryProfile      $memory      Peak memory from the dedicated memory run.
     */
    public function __construct(
        public string $command,
        public PerformanceProfile $performance,
        public MemoryProfile $memory,
    ) {}
}
