<?php

declare(strict_types=1);

namespace CarthageSoftware\StaticAnalyzersBenchmark\Benchmark;

use CarthageSoftware\StaticAnalyzersBenchmark\Support\Console;
use Psl\Async;
use Psl\DateTime;
use Psl\Math;
use Psl\Regex;
use Psl\Shell;
use Psl\Str;
use Psl\Type;

final readonly class SystemStability
{
    /**
     * Warn if CPU usage is above the given threshold.
     * Does NOT abort — just prints a warning.
     *
     * @param int<1, 100> $threshold CPU % above which to warn.
     */
    public static function warn(int $threshold = 5): void
    {
        $cpu = self::getCpuUsage();
        if ($cpu === null) {
            return;
        }

        if ($cpu > $threshold) {
            Console::warn(Str\format('System CPU is at %d%%. Benchmarks may not reflect full tool performance.', $cpu));
        }
    }

    /**
     * Check system stability by sampling CPU usage.
     *
     * @param int<1, 100> $maxCpu   Maximum acceptable CPU % per sample.
     * @param int<1, 100> $margin   Maximum allowed spread between min and max.
     * @param int<1, 20>  $samples  Number of samples to take.
     * @param int<1, 10>  $interval Seconds between samples.
     *
     * @return bool True if system is stable.
     */
    public static function check(int $maxCpu = 25, int $margin = 5, int $samples = 5, int $interval = 2): bool
    {
        Console::heading('System Stability Check');
        Console::info(Str\format(
            'Taking %d CPU samples (%ds apart), max %d%%, margin %d%%',
            $samples,
            $interval,
            $maxCpu,
            $margin,
        ));
        Console::blank();

        /** @var list<int> $readings */
        $readings = [];

        for ($i = 1; $i <= $samples; $i++) {
            $cpu = self::getCpuUsage();
            if ($cpu === null) {
                Console::error('Failed to read CPU usage');
                return false;
            }

            $readings[] = $cpu;
            Console::write(Str\format('  Sample %d/%d: %3d%% CPU', $i, $samples, $cpu));

            if ($i < $samples) {
                Async\sleep(DateTime\Duration::seconds($interval));
            }
        }

        Console::blank();

        $min = Math\min($readings) ?? 0;
        $max = Math\max($readings) ?? 0;
        $spread = $max - $min;

        // Check if any sample exceeds threshold
        foreach ($readings as $reading) {
            if ($reading <= $maxCpu) {
                continue;
            }

            Console::error(Str\format('CPU usage too high: %d%% (threshold: %d%%)', $reading, $maxCpu));
            Console::error('Close other applications and try again.');
            return false;
        }

        // Check spread
        if ($spread > $margin) {
            Console::error(Str\format(
                'CPU usage unstable: spread %d%% (min %d%%, max %d%%, allowed margin %d%%)',
                $spread,
                $min,
                $max,
                $margin,
            ));
            Console::error('System load is fluctuating. Wait for background tasks to finish and try again.');
            return false;
        }

        Console::success(Str\format(
            'System stable: %d%%-%d%% CPU (spread: %d%%, within %d%% margin)',
            $min,
            $max,
            $spread,
            $margin,
        ));

        return true;
    }

    /**
     * Get current CPU usage percentage (user + system) via `top`.
     *
     * @return null|int<0, 100> CPU percentage, or null on failure.
     */
    private static function getCpuUsage(): ?int
    {
        try {
            $output = Shell\execute('top', ['-l', '1', '-n', '0']);
        } catch (Shell\Exception\ExceptionInterface) {
            return null;
        }

        // Match: CPU usage: 5.26% user, 3.94% sys, 90.79% idle
        $matches = Regex\first_match($output, '/CPU usage:\s+([\d.]+)% user,\s+([\d.]+)% sys/');

        if ($matches === null) {
            return null;
        }

        $user = Type\float()->coerce($matches[1] ?? '0');
        $sys = Type\float()->coerce($matches[2] ?? '0');
        $total = (int) ($user + $sys);

        /** @var int<0, 100> */
        return Math\clamp($total, 0, 100);
    }
}
