<?php

declare(strict_types=1);

namespace CarthageSoftware\ToolChainBenchmarks\Profiler;

/**
 * Represents a failed command profiling attempt.
 */
final readonly class ProfileFailure
{
    /**
     * @param non-empty-string $command  The command that was run.
     * @param int              $exitCode The exit code that caused the failure (e.g. 137).
     * @param non-empty-string $reason   Human-readable failure reason.
     */
    public function __construct(
        public string $command,
        public int $exitCode,
        public string $reason,
    ) {}
}
