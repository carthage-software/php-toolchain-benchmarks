<?php

declare(strict_types=1);

namespace CarthageSoftware\ToolChainBenchmarks\Profiler\Internal;

use CarthageSoftware\ToolChainBenchmarks\Profiler\Profile\PerformanceProfile;
use CarthageSoftware\ToolChainBenchmarks\Profiler\ProfileFailure;
use Psl\Async;
use Psl\Comparison\Order;
use Psl\DateTime\Duration;
use Psl\DateTime\Timestamp;
use Psl\Iter;
use Psl\Str;

/**
 * Measures command execution time over multiple runs using proc_open.
 *
 * @internal
 */
final readonly class PerformanceProfiler
{
    /**
     * Run the command $runs times, timing each with monotonic clock.
     *
     * @param non-empty-string            $command
     * @param int<1, max>                 $runs           Number of timing runs.
     * @param null|Duration               $timeout        Timeout per run; null for no timeout.
     * @param non-empty-list<int<0, 255>> $failureCodes   Exit codes that trigger immediate failure.
     * @param null|non-empty-string       $prepareCommand Command to run before each run.
     */
    public static function measure(
        string $command,
        int $runs,
        ?Duration $timeout,
        array $failureCodes,
        ?string $prepareCommand = null,
    ): PerformanceProfile|ProfileFailure {
        /** @var list<Duration> $durations */
        $durations = [];

        for ($i = 0; $i < $runs; $i++) {
            PrepareRunner::run($prepareCommand);

            $result = self::runOnce($command, $timeout);
            if ($result instanceof ProfileFailure) {
                return $result;
            }

            [$duration, $exitCode] = $result;

            if (Iter\contains($failureCodes, $exitCode)) {
                return new ProfileFailure($command, $exitCode, Str\format('Process failed (exit code %d)', $exitCode));
            }

            $durations[] = $duration;
        }

        if ($durations === []) {
            return new ProfileFailure($command, -1, 'No successful timing runs');
        }

        return new PerformanceProfile($durations);
    }

    /**
     * Run a command once via proc_open with manual timeout handling.
     *
     * @param non-empty-string $command
     *
     * @return ProfileFailure|array{Duration, int} Duration and exit code.
     */
    private static function runOnce(string $command, ?Duration $timeout): ProfileFailure|array
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['file', '/dev/null', 'w'],
            2 => ['file', '/dev/null', 'w'],
        ];

        /** @var array<int, resource> $pipes */
        $pipes = [];
        $before = Timestamp::monotonic();

        // @mago-expect analysis:possibly-invalid-argument
        $proc = proc_open(['sh', '-c', $command], $descriptors, $pipes, '/tmp');
        if ($proc === false) {
            return new ProfileFailure($command, -1, 'Failed to start process');
        }

        fclose($pipes[0]);

        /** @var array{running: bool, exitcode: int} $status */
        $status = proc_get_status($proc);

        while ($status['running']) {
            if ($timeout !== null) {
                $elapsed = Timestamp::monotonic()->since($before);
                if ($elapsed->compare($timeout) !== Order::Less) {
                    proc_terminate($proc, 9);
                    proc_close($proc);

                    return new ProfileFailure($command, -1, 'Process timed out');
                }
            }

            Async\sleep(Duration::milliseconds(50));
            $status = proc_get_status($proc);
        }

        $after = Timestamp::monotonic();
        $exitCode = $status['exitcode'];
        proc_close($proc);

        return [$after->since($before), $exitCode];
    }
}
