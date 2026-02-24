<?php

declare(strict_types=1);

namespace CarthageSoftware\ToolChainBenchmarks\Benchmark;

use CarthageSoftware\ToolChainBenchmarks\Configuration\Project;
use CarthageSoftware\ToolChainBenchmarks\Configuration\Tool;
use CarthageSoftware\ToolChainBenchmarks\Configuration\ToolKind;

final readonly class BenchmarkFilter
{
    public function __construct(
        public ?ToolKind $kind = null,
        public ?Tool $tool = null,
        public ?Project $project = null,
    ) {}
}
