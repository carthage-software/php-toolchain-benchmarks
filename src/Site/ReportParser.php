<?php

declare(strict_types=1);

namespace CarthageSoftware\ToolChainBenchmarks\Site;

use CarthageSoftware\ToolChainBenchmarks\Support\Output;
use Psl\File;
use Psl\Filesystem;
use Psl\Json;
use Psl\Str;
use Psl\Type;
use Psl\Vec;

/**
 * Parses benchmark report.json files into normalized structures.
 *
 * Handles both old reports (using "analyzer" field, no "kinds" map) and
 * new reports (using "tool" field, with "kinds" map).
 */
final readonly class ReportParser
{
    /**
     * Default kinds map for old reports that don't include one.
     *
     * @var array<string, list<string>>
     */
    private const array DEFAULT_KINDS = [
        'Analyzers' => ['Cold', 'Hot'],
    ];

    /**
     * @param non-empty-string $resultsDir
     *
     * @return list<array{generated: string, dir: string, kinds: array<string, list<string>>, projects: array<string, array<string, list<array{tool: string, mean: float, stddev: float, min: float, max: float, memory_mb: null|float, relative: float, timed_out: bool}>>>}>
     */
    public static function loadAll(string $resultsDir): array
    {
        if (!Filesystem\is_directory($resultsDir)) {
            return [];
        }

        $runs = [];
        foreach (Filesystem\read_directory($resultsDir) as $entry) {
            if (!Filesystem\is_directory($entry)) {
                continue;
            }

            $reportPath = $entry . '/report.json';
            if (!Filesystem\is_file($reportPath)) {
                continue;
            }

            $report = self::parse($reportPath);
            if ($report !== null) {
                $runs[] = $report;
            }
        }

        return Vec\sort($runs, static fn(array $a, array $b): int => $a['generated'] <=> $b['generated']);
    }

    /**
     * @param non-empty-string $path
     *
     * @return null|array{generated: string, dir: string, kinds: array<string, list<string>>, projects: array<string, array<string, list<array{tool: string, mean: float, stddev: float, min: float, max: float, memory_mb: null|float, relative: float, timed_out: bool}>>>}
     */
    private static function parse(string $path): ?array
    {
        try {
            /** @var array<string, mixed> $raw */
            $raw = Json\decode(File\read($path));
        } catch (\Throwable) {
            Output::warn(Str\format('Skipping malformed report: %s', $path));
            return null;
        }

        $generated = Type\string()->coerce($raw['generated'] ?? '');
        if ($generated === '') {
            Output::warn(Str\format('Skipping report without generated timestamp: %s', $path));
            return null;
        }

        if (!\is_array($raw['projects'] ?? null)) {
            Output::warn(Str\format('Skipping report with invalid projects: %s', $path));
            return null;
        }

        /** @var array<string, array<string, list<array<string, mixed>>>> $rawProjects */
        $rawProjects = $raw['projects'];

        /** @var array<string, list<string>> $kinds */
        $kinds = \is_array($raw['kinds'] ?? null) ? $raw['kinds'] : self::DEFAULT_KINDS;

        return [
            'generated' => $generated,
            'dir' => Filesystem\get_filename(Filesystem\get_directory($path)),
            'kinds' => $kinds,
            'projects' => ReportNormalizer::normalizeProjects($rawProjects),
        ];
    }
}
