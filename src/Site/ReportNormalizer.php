<?php

declare(strict_types=1);

namespace CarthageSoftware\ToolChainBenchmarks\Site;

use Psl\Type;
use Psl\Vec;

/**
 * Normalizes raw benchmark report data into typed structures.
 *
 * Handles both old reports (using "analyzer" field) and new reports (using "tool" field).
 */
final readonly class ReportNormalizer
{
    /**
     * @var array<string, string>
     */
    private const array CATEGORY_ALIASES = [
        'Uncached' => 'Cold',
        'Cached' => 'Hot',
    ];

    /**
     * @param array<string, array<string, list<array<string, mixed>>>> $rawProjects
     *
     * @return array<string, array<string, list<array{tool: string, mean: float, stddev: float, min: float, max: float, memory_mb: null|float, relative: float, timed_out: bool}>>>
     */
    public static function normalizeProjects(array $rawProjects): array
    {
        /** @var array<string, array<string, list<array{tool: string, mean: float, stddev: float, min: float, max: float, memory_mb: null|float, relative: float, timed_out: bool}>>> $projects */
        $projects = [];
        foreach ($rawProjects as $projectName => $categories) {
            foreach ($categories as $catName => $entries) {
                $catName = self::CATEGORY_ALIASES[$catName] ?? $catName;
                $normalized = Vec\filter_nulls(Vec\map($entries, self::normalizeEntry(...)));
                if ($normalized !== []) {
                    $projects[$projectName][$catName] = $normalized;
                }
            }
        }

        return $projects;
    }

    /**
     * @param array<string, mixed> $entry
     *
     * @return null|array{tool: string, mean: float, stddev: float, min: float, max: float, memory_mb: null|float, relative: float, timed_out: bool}
     */
    private static function normalizeEntry(array $entry): ?array
    {
        $toolName = Type\string()->coerce($entry['tool'] ?? $entry['analyzer'] ?? '');
        if ($toolName === '') {
            return null;
        }

        return [
            'tool' => $toolName,
            'mean' => Type\float()->coerce($entry['mean'] ?? 0.0),
            'stddev' => Type\float()->coerce($entry['stddev'] ?? 0.0),
            'min' => Type\float()->coerce($entry['min'] ?? 0.0),
            'max' => Type\float()->coerce($entry['max'] ?? 0.0),
            'memory_mb' => ($entry['memory_mb'] ?? null) !== null ? Type\float()->coerce($entry['memory_mb']) : null,
            'relative' => Type\float()->coerce($entry['relative'] ?? 1.0),
            'timed_out' => (bool) ($entry['timed_out'] ?? false),
        ];
    }
}
