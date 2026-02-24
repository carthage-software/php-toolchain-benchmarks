<?php

declare(strict_types=1);

namespace CarthageSoftware\ToolChainBenchmarks\Result;

use CarthageSoftware\ToolChainBenchmarks\Configuration\Project;
use CarthageSoftware\ToolChainBenchmarks\Configuration\ToolKind;
use CarthageSoftware\ToolChainBenchmarks\Support\Output;
use CarthageSoftware\ToolChainBenchmarks\Support\Style;
use CarthageSoftware\ToolChainBenchmarks\Support\TableRenderer;
use Psl\DateTime;
use Psl\File;
use Psl\IO;
use Psl\Json;
use Psl\Str;
use Psl\Vec;

/**
 * Handles exporting benchmark results to various output formats.
 */
final readonly class ResultsExporter
{
    public function __construct(
        private Results $results,
    ) {}

    public function printFinalReport(): void
    {
        $sections = $this->results->getReportData();
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
                ['Tool', 'Mean', '± StdDev', 'Min', 'Max', 'Memory', 'Rel'],
                Vec\map($section['entries'], static fn(array $e): array => (
                    $e['timed_out']
                        ? [
                            $e['tool'],
                            '-',
                            '-',
                            '-',
                            '-',
                            '-',
                            'Timed out',
                        ] : [
                            $e['tool'],
                            Str\format('%.3fs', $e['mean']),
                            Str\format('± %.3fs', $e['stddev']),
                            Str\format('%.3fs', $e['min']),
                            Str\format('%.3fs', $e['max']),
                            $e['memory'],
                            $e['rel'],
                        ]
                )),
                '  ',
            ));
            Output::blank();
        }
    }

    /**
     * @param non-empty-string $path
     */
    public function exportJson(string $path): void
    {
        $sections = $this->results->getReportData();
        if ($sections === []) {
            return;
        }

        /** @var array<string, array<string, list<array{tool: string, mean: float, stddev: float, min: float, max: float, memory_mb: null|float, relative: float, timed_out: bool}>>> $projects */
        $projects = [];
        foreach ($sections as $section) {
            $projects[$section['project']][$section['category']] = Vec\map($section['entries'], static fn(array $e): array => [
                'tool' => $e['raw_tool'],
                'mean' => $e['mean'],
                'stddev' => $e['stddev'],
                'min' => $e['min'],
                'max' => $e['max'],
                'memory_mb' => $e['raw_memory'],
                'relative' => $e['raw_rel'],
                'timed_out' => $e['timed_out'],
            ]);
        }

        // Build the kinds map for the SiteBuilder
        $kinds = [];
        foreach (ToolKind::cases() as $kind) {
            $kinds[$kind->getDisplayName()] = $kind->getCategories();
        }

        File\write($path, Json\encode([
            'generated' => DateTime\Timestamp::now()->format('yyyy-MM-dd HH:mm:ss'),
            'kinds' => $kinds,
            'projects' => $projects,
        ], true));
    }
}
