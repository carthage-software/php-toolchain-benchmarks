#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use CarthageSoftware\ToolChainBenchmarks\Profiler\CommandProfiler;
use CarthageSoftware\ToolChainBenchmarks\Profiler\Profile\CommandProfile;
use Psl\DateTime\Duration;
use Psl\IO;
use Psl\Iter;
use Psl\Str;

$profiler = new CommandProfiler(runs: 10, timeout: Duration::minutes(5));
$result = $profiler->profile('php --version');

if (!$result instanceof CommandProfile) {
    IO\write_error_line('Profiling failed: ' . $result->reason);
    exit(1);
}

$perf = $result->performance;

IO\write_line('Command: ' . $result->command);
IO\write_line('');
IO\write_line(Str\format('Performance (%d runs):', Iter\count($perf->durations)));
IO\write_line(Str\format('  Mean:   %.3fs', $perf->mean()->getTotalSeconds()));
IO\write_line(Str\format('  StdDev: %.3fs', $perf->stddev()->getTotalSeconds()));
IO\write_line(Str\format('  Min:    %.3fs', $perf->min()->getTotalSeconds()));
IO\write_line(Str\format('  Max:    %.3fs', $perf->max()->getTotalSeconds()));
IO\write_line('');
IO\write_line('Memory:');
IO\write_line('  Peak:   ' . $result->memory->format());
