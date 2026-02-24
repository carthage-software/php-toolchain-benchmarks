<?php

declare(strict_types=1);

namespace CarthageSoftware\ToolChainBenchmarks\Benchmark;

use CarthageSoftware\ToolChainBenchmarks\Configuration\ToolInstance;
use CarthageSoftware\ToolChainBenchmarks\Result\Results;

/**
 * Groups the shared dependencies for a benchmark run against a single project.
 */
final readonly class RunContext
{
    /**
     * @param list<ToolInstance> $tools
     */
    public function __construct(
        public array $tools,
        public ProjectContext $project,
        public Results $results,
    ) {}
}
