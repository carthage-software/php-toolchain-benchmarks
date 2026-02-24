<?php

declare(strict_types=1);

namespace CarthageSoftware\ToolChainBenchmarks\Benchmark;

use CarthageSoftware\ToolChainBenchmarks\Support\Output;
use CarthageSoftware\ToolChainBenchmarks\Support\Spinner;
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
     * @param int<1, 100> $threshold
     */
    public static function warn(int $threshold = 5): void
    {
        $cpu = self::getCpuUsage();
        if ($cpu === null) {
            return;
        }

        if ($cpu > $threshold) {
            Output::warn(Str\format('System CPU at %d%%', $cpu));
        }
    }

    /**
     * @param int<1, 100> $maxCpu
     * @param int<1, 100> $margin
     * @param int<1, 20>  $samples
     * @param int<1, 10>  $interval
     */
    public static function check(int $maxCpu = 25, int $margin = 5, int $samples = 5, int $interval = 2): bool
    {
        $spinner = new Spinner('Checking system stability...', '  ');

        /** @var list<int> $readings */
        $readings = [];

        for ($i = 1; $i <= $samples; $i++) {
            $cpu = self::getCpuUsage();
            if ($cpu === null) {
                $spinner->fail('Failed to read CPU usage');
                return false;
            }

            $readings[] = $cpu;
            $spinner->tick(Str\format('Checking system stability... (%d/%d, %d%% CPU)', $i, $samples, $cpu));

            if ($i < $samples) {
                Async\sleep(DateTime\Duration::seconds($interval));
            }
        }

        $min = Math\min($readings) ?? 0;
        $max = Math\max($readings) ?? 0;
        $spread = $max - $min;

        $tooHigh = Math\max($readings) ?? 0;
        if ($tooHigh > $maxCpu) {
            $spinner->fail(Str\format('CPU too high: %d%% (threshold: %d%%)', $tooHigh, $maxCpu));
            Output::error('Close other applications and try again.');
            return false;
        }

        if ($spread > $margin) {
            $spinner->fail(Str\format(
                'CPU unstable: spread %d%% (%d%%-%d%%, margin %d%%)',
                $spread,
                $min,
                $max,
                $margin,
            ));
            Output::error('System load is fluctuating. Wait for background tasks to finish.');
            return false;
        }

        $spinner->succeed(Str\format('System stable: %d%%-%d%% CPU, spread %d%%', $min, $max, $spread));

        return true;
    }

    /**
     * @return null|int<0, 100>
     */
    private static function getCpuUsage(): ?int
    {
        try {
            $output = Shell\execute('top', ['-l', '1', '-n', '0']);
        } catch (Shell\Exception\ExceptionInterface) {
            return null;
        }

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
