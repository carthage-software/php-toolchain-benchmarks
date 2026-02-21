<?php

declare(strict_types=1);

namespace CarthageSoftware\StaticAnalyzersBenchmark\Result;

use Psl\Math;
use Psl\Str;
use Psl\Vec;

/**
 * Annotates benchmark entries with winner emoji and relative performance.
 */
final readonly class WinnerAnnotator
{
    /**
     * @param list<array{analyzer: string, raw_analyzer: string, mean: float, stddev: float, min: float, max: float, memory: string, raw_memory: null|float, rel: string, raw_rel: float}> $entries
     *
     * @return list<array{analyzer: string, raw_analyzer: string, mean: float, stddev: float, min: float, max: float, memory: string, raw_memory: null|float, rel: string, raw_rel: float}>
     */
    public static function annotate(array $entries): array
    {
        $minMean = Math\min(Vec\map($entries, static fn(array $e): float => $e['mean']));
        $memValues = Vec\filter_nulls(Vec\map($entries, static fn(array $e): ?float => $e['raw_memory']));
        $minMem = $memValues !== [] ? Math\min($memValues) : null;

        return Vec\map($entries, static function (array $e) use ($minMean, $minMem): array {
            $rel = $minMean !== null && $minMean > 0.0 ? $e['mean'] / $minMean : 1.0;
            $e['raw_rel'] = Math\round($rel, 1);

            $isTimeWinner = $minMean !== null && $e['mean'] === $minMean;
            $isMemWinner = $minMem !== null && $e['raw_memory'] !== null && $e['raw_memory'] === $minMem;

            $e['analyzer'] = $isTimeWinner ? $e['analyzer'] . ' 🏆' : $e['analyzer'];
            $e['memory'] = $isMemWinner ? $e['memory'] . ' 🎉' : $e['memory'];
            $e['rel'] = $isTimeWinner ? '' : Str\format('x%.1f', $rel);

            return $e;
        });
    }
}
