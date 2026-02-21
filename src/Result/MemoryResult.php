<?php

declare(strict_types=1);

namespace CarthageSoftware\StaticAnalyzersBenchmark\Result;

use Psl\Async;
use Psl\DateTime\Duration;
use Psl\Iter;
use Psl\Math;
use Psl\Regex;
use Psl\Str;

/**
 * Measures peak memory usage of a command by sampling the entire process tree's RSS.
 *
 * Unlike /usr/bin/time -l (which only reports the single largest process),
 * this approach sums RSS across all descendant processes at each sample point,
 * capturing the true memory footprint of tools like PHPStan and Psalm that
 * "fork" worker processes for parallel analysis.
 */
final readonly class MemoryResult
{
    /**
     * @param non-empty-string $analyzerName
     * @param null|float       $peakMemoryMb Peak total RSS in MB, null if measurement failed.
     */
    public function __construct(
        public string $analyzerName,
        public ?float $peakMemoryMb,
    ) {}

    /**
     * Measure peak memory for a shell command by sampling the process tree.
     *
     * @param non-empty-string $analyzerName
     * @param non-empty-string $command
     */
    public static function measure(string $analyzerName, string $command): self
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['file', '/dev/null', 'w'],
            2 => ['file', '/dev/null', 'w'],
        ];

        /** @var array<int, resource> $pipes */
        $pipes = [];
        // @mago-expect analysis:possibly-invalid-argument - stupid static analyzer!
        $proc = proc_open(['sh', '-c', $command], $descriptors, $pipes, '/tmp');
        if ($proc === false) {
            return new self($analyzerName, null);
        }

        fclose($pipes[0]);

        /** @var array{running: bool, pid: int} $status */
        $status = proc_get_status($proc);
        $pid = $status['pid'];
        $peakRssKb = 0;

        while ($status['running']) {
            $rss = self::getProcessTreeRssKb($pid);
            $peakRssKb = Math\maxva($peakRssKb, $rss);

            Async\sleep(Duration::milliseconds(100));
            $status = proc_get_status($proc);
        }

        // Final sample — catch any last-moment memory spike
        $rss = self::getProcessTreeRssKb($pid);
        $peakRssKb = Math\maxva($peakRssKb, $rss);

        proc_close($proc);

        if ($peakRssKb === 0) {
            return new self($analyzerName, null);
        }

        return new self($analyzerName, Math\round($peakRssKb / 1024, 1));
    }

    public function formatMb(): string
    {
        if ($this->peakMemoryMb === null) {
            return 'N/A';
        }

        return Str\format('%.1f', $this->peakMemoryMb);
    }

    /**
     * Get the total RSS (in KB) of a process and all its descendants.
     */
    private static function getProcessTreeRssKb(int $rootPid): int
    {
        $output = shell_exec('ps -eo pid,ppid,rss');
        if ($output === null || $output === false) {
            return 0;
        }

        // Parse ps output into [pid => [ppid, rss]] map
        /** @var array<int, array{int, int}> $processes ppid, rss indexed by pid */
        $processes = [];
        /** @var array<int, list<int>> $children pid => list of child pids */
        $children = [];

        foreach (Str\split(Str\trim($output), "\n") as $line) {
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
