<?php

declare(strict_types=1);

namespace CarthageSoftware\StaticAnalyzersBenchmark\Benchmark;

use CarthageSoftware\StaticAnalyzersBenchmark\Configuration\Analyzer;
use CarthageSoftware\StaticAnalyzersBenchmark\Configuration\BenchmarkCategory;
use CarthageSoftware\StaticAnalyzersBenchmark\Configuration\Project;
use CarthageSoftware\StaticAnalyzersBenchmark\Result\BenchmarkResults;
use CarthageSoftware\StaticAnalyzersBenchmark\Result\Summary;
use CarthageSoftware\StaticAnalyzersBenchmark\Support\Console;
use Psl\Filesystem;
use Psl\Str;
use Psl\Vec;

final readonly class Benchmark
{
    /**
     * @param non-empty-string $rootDir
     * @param int<1, max>      $runs
     * @param int<0, max>      $warmup
     */
    public function __construct(
        private string $rootDir,
        private int $runs = 10,
        private int $warmup = 2,
        private bool $skipStability = false,
        private BenchmarkFilter $filter = new BenchmarkFilter(),
    ) {}

    public function execute(): int
    {
        $workspaceDir = $this->rootDir . '/workspace';
        $cacheDir = $this->rootDir . '/cache';

        $analyzers = Discovery::analyzers($this->rootDir, $this->filter->analyzer);
        if ($analyzers === []) {
            Console::error('No analyzers available. Run: bin/benchmark setup');
            return 1;
        }

        $projects = Discovery::projects($workspaceDir, $this->filter->project);
        if ($projects === []) {
            Console::error('No projects available. Run: bin/benchmark setup');
            return 1;
        }

        $timestamp = \date('Ymd-His');
        $resultsDir = Str\format('%s/results/%s', $this->rootDir, $timestamp);
        Filesystem\create_directory($resultsDir);

        Console::heading('PHP Static Analyzer Benchmarks');
        Console::info(Str\format('Runs: %d, Warmup: %d', $this->runs, $this->warmup));
        Console::info(Str\format('Analyzers: %s', Str\join(
            Vec\map($analyzers, static fn(Analyzer $a): string => $a->value),
            ' ',
        )));
        Console::info(Str\format('Projects: %s', Str\join(
            Vec\map($projects, static fn(Project $p): string => $p->value),
            ' ',
        )));
        Console::info(Str\format('Results: %s', $resultsDir));

        SystemStability::warn();

        if ($this->skipStability) {
            Console::warn('Skipping system stability check (--skip-stability)');
        }

        if (!$this->skipStability && !SystemStability::check()) {
            Console::error('Aborting benchmarks due to unstable system.');
            Console::error('Use --skip-stability to bypass this check.');
            return 1;
        }

        $summary = new Summary($resultsDir);
        $summary->writeHeader($this->runs, $this->warmup);

        $results = new BenchmarkResults();
        $runner = new CategoryRunner($this->runs, $this->warmup);
        $category = $this->filter->category;

        foreach ($projects as $project) {
            $ws = Str\format('%s/%s', $workspaceDir, $project->value);
            $projectResultsDir = Str\format('%s/%s', $resultsDir, $project->value);
            Filesystem\create_directory($projectResultsDir);

            Console::heading(Str\format('Benchmarking: %s', $project->getDisplayName()));
            $summary->writeProjectHeading($project);

            $ctx = new ProjectContext($this->rootDir, $project, $ws, $cacheDir, $projectResultsDir);

            if ($category === null || $category === BenchmarkCategory::Uncached) {
                $runner->runUncached($analyzers, $ctx, $summary, $results);
            }

            if ($category === null || $category === BenchmarkCategory::Incremental) {
                $runner->runIncremental($analyzers, $ctx, $summary, $results);
            }
        }

        $results->printFinalReport();
        $results->exportMarkdown($resultsDir . '/report.md');
        $results->exportJson($resultsDir . '/report.json');

        Console::info(Str\format('Results saved to: %s', $resultsDir));
        Console::info(Str\format('Summary: %s', $summary->getPath()));
        Console::heading('Benchmarks complete');

        return 0;
    }
}
