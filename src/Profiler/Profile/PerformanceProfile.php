<?php

declare(strict_types=1);

namespace CarthageSoftware\ToolChainBenchmarks\Profiler\Profile;

use Psl\DateTime\Duration;
use Psl\Iter;
use Psl\Math;
use Psl\Vec;

/**
 * Performance measurement results: a collection of durations with statistical accessors.
 */
final readonly class PerformanceProfile
{
    /**
     * @param non-empty-list<Duration> $durations
     */
    public function __construct(
        public array $durations,
    ) {}

    public function min(): Duration
    {
        $seconds = Vec\map($this->durations, static fn(Duration $d): float => $d->getTotalSeconds());
        $minVal = Math\min($seconds);

        foreach ($this->durations as $i => $d) {
            if ($seconds[$i] === $minVal) {
                return $d;
            }
        }

        return $this->durations[0];
    }

    public function max(): Duration
    {
        $seconds = Vec\map($this->durations, static fn(Duration $d): float => $d->getTotalSeconds());
        $maxVal = Math\max($seconds);

        foreach ($this->durations as $i => $d) {
            if ($seconds[$i] === $maxVal) {
                return $d;
            }
        }

        return $this->durations[0];
    }

    public function mean(): Duration
    {
        $seconds = Vec\map($this->durations, static fn(Duration $d): float => $d->getTotalSeconds());
        $mean = Math\mean($seconds);

        return Duration::nanoseconds((int) ($mean * 1_000_000_000));
    }

    public function stddev(): Duration
    {
        $seconds = Vec\map($this->durations, static fn(Duration $d): float => $d->getTotalSeconds());
        $mean = Math\mean($seconds);
        $count = Iter\count($seconds);

        $sumSquaredDiffs = 0.0;
        foreach ($seconds as $s) {
            $diff = $s - $mean;
            $sumSquaredDiffs += $diff * $diff;
        }

        $stddev = Math\sqrt($sumSquaredDiffs / $count);

        return Duration::nanoseconds((int) ($stddev * 1_000_000_000));
    }
}
