<?php

declare(strict_types=1);

namespace CarthageSoftware\ToolChainBenchmarks\Site;

use CarthageSoftware\ToolChainBenchmarks\Configuration\ToolKind;
use CarthageSoftware\ToolChainBenchmarks\Support\Output;
use Psl\DateTime;
use Psl\File;
use Psl\Filesystem;
use Psl\Iter;
use Psl\Json;
use Psl\Str;

/**
 * Builds a single self-contained HTML dashboard from all benchmark results.
 *
 * Reads every results/YYYYMMDD-HHMMSS/report.json, aggregates them, and
 * produces results/index.html with inline CSS/JS.
 */
final readonly class SiteBuilder
{
    /**
     * @param non-empty-string $rootDir
     */
    public static function run(string $rootDir): int
    {
        $resultsDir = $rootDir . '/results';

        Output::section('Building results page');

        $runs = ReportParser::loadAll($resultsDir);
        if ($runs === []) {
            Output::error('No benchmark results found in results/');
            return 1;
        }

        self::writeOutputFiles($resultsDir, $runs);

        Output::success(Str\format(
            'Built results page: %s (%d run(s))',
            $resultsDir . '/index.html',
            Iter\count($runs),
        ));

        return 0;
    }

    /**
     * @param non-empty-string $resultsDir
     * @param non-empty-list<array{generated: string, dir: string, kinds: array<string, list<string>>, projects: array<string, array<string, list<array{tool: string, mean: float, stddev: float, min: float, max: float, memory_mb: null|float, relative: float, timed_out: bool}>>>}> $runs
     */
    private static function writeOutputFiles(string $resultsDir, array $runs): void
    {
        $latest = self::buildMergedLatest($runs);

        $outputPath = $resultsDir . '/index.html';
        if (Filesystem\is_file($outputPath)) {
            Filesystem\delete_file($outputPath);
        }

        File\write($outputPath, HtmlRenderer::render($latest, $runs), File\WriteMode::MustCreate);

        $latestPath = $resultsDir . '/latest.json';
        if (Filesystem\is_file($latestPath)) {
            Filesystem\delete_file($latestPath);
        }

        File\write($latestPath, Json\encode($latest, true), File\WriteMode::MustCreate);
    }

    /**
     * Merge all runs into an aggregated structure keyed by project → category → tool name,
     * where each tool has a list of all its runs (newest first).
     *
     * @param non-empty-list<array{generated: string, dir: string, kinds: array<string, list<string>>, projects: array<string, array<string, list<array{tool: string, mean: float, stddev: float, min: float, max: float, memory_mb: null|float, relative: float, timed_out: bool}>>>}> $runs
     *
     * @return array{
     *     "aggregation-date": string,
     *     kinds: array<string, list<string>>,
     *     projects: array<string, array<string, array<string, list<array{date: string, mean: float, stddev: float, min: float, max: float, memory_mb: null|float, relative: float, timed_out: bool}>>>>
     * }
     */
    private static function buildMergedLatest(array $runs): array
    {
        $kinds = [];
        foreach (ToolKind::cases() as $kind) {
            $kinds[$kind->getDisplayName()] = $kind->getCategories();
        }

        /** @var array<string, array<string, array<string, list<array{date: string, mean: float, stddev: float, min: float, max: float, memory_mb: null|float, relative: float, timed_out: bool}>>>> $projects */
        $projects = [];

        // Iterate runs newest-first so entries are ordered newest first per tool
        for ($i = Iter\count($runs) - 1; $i >= 0; $i--) {
            $run = $runs[$i];
            $date = $run['generated'];

            foreach ($run['projects'] as $proj => $categories) {
                foreach ($categories as $cat => $entries) {
                    foreach ($entries as $entry) {
                        $projects[$proj][$cat][$entry['tool']][] = [
                            'date' => $date,
                            'mean' => $entry['mean'],
                            'stddev' => $entry['stddev'],
                            'min' => $entry['min'],
                            'max' => $entry['max'],
                            'memory_mb' => $entry['memory_mb'],
                            'relative' => $entry['relative'],
                            'timed_out' => $entry['timed_out'],
                        ];
                    }
                }
            }
        }

        return [
            'aggregation-date' => DateTime\Timestamp::now()->format('yyyy-MM-dd HH:mm:ss'),
            'kinds' => $kinds,
            'projects' => $projects,
        ];
    }
}
