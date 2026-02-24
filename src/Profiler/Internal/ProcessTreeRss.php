<?php

declare(strict_types=1);

namespace CarthageSoftware\ToolChainBenchmarks\Profiler\Internal;

use Psl\Iter;
use Psl\Regex;
use Psl\Shell;
use Psl\Str;

/**
 * Reads the total RSS (in KB) of a process and all its descendants.
 *
 * Parses `ps -eo pid,ppid,rss` output and walks the process tree via BFS.
 *
 * @internal
 */
final readonly class ProcessTreeRss
{
    /**
     * Get the total RSS (in KB) of a process and all its descendants.
     */
    public static function measure(int $rootPid): int
    {
        try {
            $output = Shell\execute('ps', ['-eo', 'pid,ppid,rss']);
        } catch (Shell\Exception\FailedExecutionException) {
            return 0;
        }

        return self::sumTreeRss($rootPid, $output);
    }

    /**
     * Parse ps output and sum RSS for the given root PID and all descendants.
     */
    private static function sumTreeRss(int $rootPid, string $psOutput): int
    {
        /** @var array<int, array{int, int}> $processes ppid, rss indexed by pid */
        $processes = [];
        /** @var array<int, list<int>> $children pid => list of child pids */
        $children = [];

        foreach (Str\split(Str\trim($psOutput), "\n") as $line) {
            $parts = Regex\split(Str\trim($line), '/\s+/');
            if (Iter\count($parts) < 3) {
                continue;
            }

            $pid = (int) $parts[0];
            $ppid = (int) $parts[1];
            $rss = (int) $parts[2];

            if ($pid === 0) {
                continue;
            }

            $processes[$pid] = [$ppid, $rss];
            $children[$ppid][] = $pid;
        }

        if (($processes[$rootPid] ?? null) === null) {
            return 0;
        }

        // BFS to sum RSS of root + all descendants
        $totalRss = 0;
        $queue = [$rootPid];
        while ($queue !== []) {
            $current = array_shift($queue);
            $totalRss += $processes[$current][1];

            foreach ($children[$current] ?? [] as $childPid) {
                $queue[] = $childPid;
            }
        }

        return $totalRss;
    }
}
