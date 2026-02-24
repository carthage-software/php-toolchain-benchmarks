<?php

declare(strict_types=1);

namespace CarthageSoftware\ToolChainBenchmarks\Result;

use Psl\Math;
use Psl\Str;
use Psl\Vec;

/**
 * Annotates benchmark entries with winner emoji and relative performance.
 */
final readonly class WinnerAnnotator
{
    /**
     * @param list<array{tool: string, raw_tool: string, mean: float, stddev: float, min: float, max: float, memory: string, raw_memory: null|float, rel: string, raw_rel: float, timed_out: bool}> $entries
     *
     * @return list<array{tool: string, raw_tool: string, mean: float, stddev: float, min: float, max: float, memory: string, raw_memory: null|float, rel: string, raw_rel: float, timed_out: bool}>
     */
    public static function annotate(array $entries): array
    {
        // Only consider non-timed-out entries for winner calculations
        $valid = Vec\filter($entries, static fn(array $e): bool => !$e['timed_out']);

        $minMean = $valid !== [] ? Math\min(Vec\map($valid, static fn(array $e): float => $e['mean'])) : null;
        $memValues = Vec\filter_nulls(Vec\map($valid, static fn(array $e): ?float => $e['raw_memory']));
        $minMem = $memValues !== [] ? Math\min($memValues) : null;

        // Sort: timed-out entries go last, others sorted by mean
        $entries = Vec\sort($entries, static fn(array $a, array $b): int => match (true) {
            $a['timed_out'] && $b['timed_out'] => 0,
            $a['timed_out'] => 1,
            $b['timed_out'] => -1,
            default => $a['mean'] <=> $b['mean'],
        });

        return Vec\map($entries, static function (array $e) use ($minMean, $minMem): array {
            if ($e['timed_out']) {
                $e['rel'] = 'Timed out';
                return $e;
            }

            $rel = $minMean !== null && $minMean > 0.0 ? $e['mean'] / $minMean : 1.0;
            $e['raw_rel'] = Math\round($rel, 1);

            $isTimeWinner = $minMean !== null && $e['mean'] === $minMean;
            $isMemWinner = $minMem !== null && $e['raw_memory'] !== null && $e['raw_memory'] === $minMem;

            $e['tool'] = $isTimeWinner ? $e['tool'] . ' 🏆' : $e['tool'];
            $e['memory'] = $isMemWinner ? $e['memory'] . ' 🎉' : $e['memory'];
            $e['rel'] = $isTimeWinner ? '' : Str\format('x%.1f', $rel);

            return $e;
        });
    }
}
