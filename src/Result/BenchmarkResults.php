<?php

declare(strict_types=1);

namespace CarthageSoftware\StaticAnalyzersBenchmark\Result;

use CarthageSoftware\StaticAnalyzersBenchmark\Configuration\Project;
use CarthageSoftware\StaticAnalyzersBenchmark\Support\Output;
use CarthageSoftware\StaticAnalyzersBenchmark\Support\Style;
use CarthageSoftware\StaticAnalyzersBenchmark\Support\TableRenderer;
use Psl\Dict;
use Psl\File;
use Psl\IO;
use Psl\Json;
use Psl\Str;
use Psl\Vec;

final class BenchmarkResults
{
    /**
     * @var list<array{project: Project, category: string, analyzer: string, mean: float, stddev: float, min: float, max: float}>
     */
    private array $timings = [];

    /**
     * @var list<array{project: Project, category: string, analyzer: string, peakMb: null|float}>
     */
    private array $memory = [];

    /**
     * @param array{command: string, mean: float, stddev: null|float, min: float, max: float, ...} $entry
     */
    public function addTiming(Project $project, string $category, array $entry): void
    {
        $this->timings[] = [
            'project' => $project,
            'category' => $category,
            'analyzer' => $entry['command'],
            'mean' => $entry['mean'],
            'stddev' => $entry['stddev'] ?? 0.0,
            'min' => $entry['min'],
            'max' => $entry['max'],
        ];
    }

    public function addMemory(Project $project, string $category, string $analyzer, ?float $peakMb): void
    {
        $this->memory[] = [
            'project' => $project,
            'category' => $category,
            'analyzer' => $analyzer,
            'peakMb' => $peakMb,
        ];
    }

    public function printFinalReport(): void
    {
        $sections = $this->buildReportData();
        if ($sections === []) {
            return;
        }

        IO\write_line('  %s', Str\repeat(Style::RULE_THICK, 46));
        Output::blank();
        Output::title('Final Report');
        $currentProject = '';

        foreach ($sections as $section) {
            if ($section['project'] !== $currentProject) {
                Output::write(Str\format('  ── %s ──', Project::from($section['project'])->getDisplayName()));
                Output::blank();
                $currentProject = $section['project'];
            }

            Output::write(Str\format('  %s', $section['category']));
            Output::write(TableRenderer::render(
                ['Analyzer', 'Mean', '± StdDev', 'Min', 'Max', 'Memory', 'Rel'],
                Vec\map($section['entries'], static fn(array $e): array => [
                    $e['analyzer'],
                    Str\format('%.3fs', $e['mean']),
                    Str\format('± %.3fs', $e['stddev']),
                    Str\format('%.3fs', $e['min']),
                    Str\format('%.3fs', $e['max']),
                    $e['memory'],
                    $e['rel'],
                ]),
                '  ',
            ));
            Output::blank();
        }
    }

    /**
     * @param non-empty-string $path
     */
    public function exportMarkdown(string $path): void
    {
        $sections = $this->buildReportData();
        if ($sections === []) {
            return;
        }

        $content = Str\format("# Benchmark Report\n\nGenerated: %s\n\n", \date('Y-m-d H:i:s'));
        $currentProject = '';

        foreach ($sections as $section) {
            if ($section['project'] !== $currentProject) {
                $content .= Str\format("## %s\n\n", Project::from($section['project'])->getDisplayName());
                $currentProject = $section['project'];
            }

            $content .= Str\format("### %s\n\n", $section['category']);
            $content .= "| Analyzer | Mean | ± StdDev | Min | Max | Memory | Rel |\n";
            $content .= "|----------|------|----------|-----|-----|--------|-----|\n";
            $content .= Str\join(Vec\map($section['entries'], static fn(array $e): string => Str\format(
                '| %s | %.3fs | ± %.3fs | %.3fs | %.3fs | %s | %s |',
                $e['analyzer'],
                $e['mean'],
                $e['stddev'],
                $e['min'],
                $e['max'],
                $e['memory'],
                $e['rel'],
            )), "\n");
            $content .= "\n\n";
        }

        File\write($path, $content);
    }

    /**
     * @param non-empty-string $path
     */
    public function exportJson(string $path): void
    {
        $sections = $this->buildReportData();
        if ($sections === []) {
            return;
        }

        /** @var array<string, array<string, list<array{analyzer: string, mean: float, stddev: float, min: float, max: float, memory_mb: null|float, relative: float}>>> $projects */
        $projects = [];
        foreach ($sections as $section) {
            $projects[$section['project']][$section['category']] = Vec\map($section['entries'], static fn(array $e): array => [
                'analyzer' => $e['raw_analyzer'],
                'mean' => $e['mean'],
                'stddev' => $e['stddev'],
                'min' => $e['min'],
                'max' => $e['max'],
                'memory_mb' => $e['raw_memory'],
                'relative' => $e['raw_rel'],
            ]);
        }

        File\write($path, Json\encode(['generated' => \date('Y-m-d H:i:s'), 'projects' => $projects], true));
    }

    /**
     * @return list<array{project: string, category: string, entries: list<array{analyzer: string, raw_analyzer: string, mean: float, stddev: float, min: float, max: float, memory: string, raw_memory: null|float, rel: string, raw_rel: float}>}>
     */
    private function buildReportData(): array
    {
        /** @var array<string, null|float> $memLookup */
        $memLookup = Dict\pull(
            $this->memory,
            static fn(array $m): ?float => $m['peakMb'],
            static fn(array $m): string => $m['project']->value . ':' . $m['category'] . ':' . $m['analyzer'],
        );

        /** @var array<string, array{project: string, category: string, entries: list<array{analyzer: string, raw_analyzer: string, mean: float, stddev: float, min: float, max: float, memory: string, raw_memory: null|float, rel: string, raw_rel: float}>}> $groups */
        $groups = [];
        foreach ($this->timings as $t) {
            $groupKey = $t['project']->value . ':' . $t['category'];
            $mem = $memLookup[$groupKey . ':' . $t['analyzer']] ?? null;

            $groups[$groupKey] ??= ['project' => $t['project']->value, 'category' => $t['category'], 'entries' => []];
            $groups[$groupKey]['entries'][] = [
                'analyzer' => $t['analyzer'],
                'raw_analyzer' => $t['analyzer'],
                'mean' => $t['mean'],
                'stddev' => $t['stddev'],
                'min' => $t['min'],
                'max' => $t['max'],
                'memory' => $mem !== null ? Str\format('%.1f MB', $mem) : '-',
                'raw_memory' => $mem,
                'rel' => '',
                'raw_rel' => 1.0,
            ];
        }

        return Vec\map(Vec\values($groups), static function (array $group): array {
            $group['entries'] = WinnerAnnotator::annotate($group['entries']);

            return $group;
        });
    }
}
