<?php

declare(strict_types=1);

namespace CarthageSoftware\ToolChainBenchmarks\Profiler\Profile;

use Psl\Str;

/**
 * Peak memory measurement from a single dedicated profiling run.
 */
final readonly class MemoryProfile
{
    /**
     * @param float $peakMb Peak RSS in megabytes.
     */
    public function __construct(
        public float $peakMb,
    ) {}

    /**
     * @return non-empty-string
     */
    public function format(): string
    {
        return Str\format('%.1f MB', $this->peakMb);
    }
}
