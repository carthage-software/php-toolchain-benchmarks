<?php

declare(strict_types=1);

namespace CarthageSoftware\ToolChainBenchmarks\Profiler\Internal;

use CarthageSoftware\ToolChainBenchmarks\Profiler\Profile\MemoryProfile;
use CarthageSoftware\ToolChainBenchmarks\Profiler\ProfileFailure;
use Psl\Async;
use Psl\Comparison\Order;
use Psl\DateTime\Duration;
use Psl\DateTime\Timestamp;
use Psl\Iter;
use Psl\Math;
use Psl\Regex;
use Psl\Shell;
use Psl\Str;

/**
 * Measures peak memory usage of a command by sampling the process tree's RSS.
 *
 * Uses proc_open to launch the command and polls RSS via {@see ProcessTreeRss}.
 * Falls back to `/usr/bin/time -l` for fast processes where polling misses the peak.
 *
 * @internal
 */
final readonly class MemoryProfiler
{
    /**
     * Measure peak memory for a shell command.
     *
     * @param non-empty-string            $command
     * @param null|Duration               $timeout        Timeout per run; null for no timeout.
     * @param non-empty-list<int<0, 255>> $failureCodes   Exit codes that trigger immediate failure.
     * @param null|non-empty-string       $prepareCommand Command to run before measurement.
     */
    public static function measure(
        string $command,
        ?Duration $timeout,
        array $failureCodes,
        ?string $prepareCommand = null,
    ): MemoryProfile|ProfileFailure {
        PrepareRunner::run($prepareCommand);

        $result = self::measureViaPolling($command, $timeout);
        if ($result instanceof ProfileFailure) {
            return $result;
        }

        [$exitCode, $peakMb] = $result;

        if (Iter\contains($failureCodes, $exitCode)) {
            return new ProfileFailure($command, $exitCode, Str\format('Process failed (exit code %d)', $exitCode));
        }

        // Polling can miss peaks between samples — cross-check with /usr/bin/time
        if ($peakMb === null || $peakMb < 100.0) {
            PrepareRunner::run($prepareCommand);
            $timeMb = self::measureWithTime($command) ?? 0.0;
            $peakMb = Math\maxva($peakMb ?? 0.0, $timeMb);
        }

        return new MemoryProfile($peakMb);
    }

    /**
     * Launch the command via proc_open and poll RSS until it exits.
     *
     * @param non-empty-string $command
     *
     * @return ProfileFailure|array{int, null|float} Exit code and peak MB (null if no reading).
     */
    private static function measureViaPolling(string $command, ?Duration $timeout): ProfileFailure|array
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['file', '/dev/null', 'w'],
            2 => ['file', '/dev/null', 'w'],
        ];

        /** @var array<int, resource> $pipes */
        $pipes = [];
        // @mago-expect analysis:possibly-invalid-argument
        $proc = proc_open(['sh', '-c', $command], $descriptors, $pipes, '/tmp');
        if ($proc === false) {
            return new ProfileFailure($command, -1, 'Failed to start process');
        }

        fclose($pipes[0]);

        /** @var array{running: bool, pid: int, exitcode: int} $status */
        $status = proc_get_status($proc);
        $pid = $status['pid'];
        $peakRssKb = 0;
        $startTime = Timestamp::monotonic();

        while ($status['running']) {
            if ($timeout !== null) {
                $elapsed = Timestamp::monotonic()->since($startTime);
                if ($elapsed->compare($timeout) !== Order::Less) {
                    proc_terminate($proc, 9);
                    proc_close($proc);

                    return new ProfileFailure($command, 137, 'Memory measurement timed out');
                }
            }

            $peakRssKb = Math\maxva($peakRssKb, ProcessTreeRss::measure($pid));

            Async\sleep(Duration::milliseconds(50));
            $status = proc_get_status($proc);
        }

        // Final sample — catch any last-moment memory spike
        $peakRssKb = Math\maxva($peakRssKb, ProcessTreeRss::measure($pid));

        $exitCode = $status['exitcode'];
        proc_close($proc);

        $peakMb = $peakRssKb > 0 ? Math\round($peakRssKb / 1024, 1) : null;

        return [$exitCode, $peakMb];
    }

    /**
     * Re-run the command under /usr/bin/time -l to get kernel-reported peak RSS.
     *
     * @param non-empty-string $command
     */
    private static function measureWithTime(string $command): ?float
    {
        try {
            $output = Shell\execute('sh', [
                '-c',
                Str\format('/usr/bin/time -l sh -c %s 2>&1', \escapeshellarg($command . ' >/dev/null 2>&1')),
            ]);
        } catch (Shell\Exception\FailedExecutionException $e) {
            $output = $e->getOutput();
        }

        // macOS: "  1234567  maximum resident set size" (bytes)
        $matches = Regex\first_match($output, '/(\d+)\s+maximum resident set size/');
        $bytes = (int) ($matches[1] ?? '0');

        return $bytes > 0 ? Math\round(($bytes / 1024) / 1024, 1) : null;
    }
}
