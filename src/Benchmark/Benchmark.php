<?php

declare(strict_types=1);

namespace CarthageSoftware\StaticAnalyzersBenchmark\Benchmark;

use CarthageSoftware\StaticAnalyzersBenchmark\Configuration\AnalyzerTool;
use CarthageSoftware\StaticAnalyzersBenchmark\Configuration\BenchmarkCategory;
use CarthageSoftware\StaticAnalyzersBenchmark\Configuration\Project;
use CarthageSoftware\StaticAnalyzersBenchmark\Configuration\ToolPaths;
use CarthageSoftware\StaticAnalyzersBenchmark\Result\BenchmarkResults;
use CarthageSoftware\StaticAnalyzersBenchmark\Result\Summary;
use CarthageSoftware\StaticAnalyzersBenchmark\Support\Output;
use Psl\DateTime;
use Psl\Filesystem;
use Psl\Iter;
use Psl\Str;
use Psl\Vec;

final readonly class Benchmark
{
    /**
     * @param int<1, max> $runs
     * @param int<0, max> $warmup
     */
    public function __construct(
        private ToolPaths $tools,
        private int $runs = 10,
        private int $warmup = 2,
        private bool $skipStability = false,
        private BenchmarkFilter $filter = new BenchmarkFilter(),
    ) {}

    public function execute(): int
    {
        $overallStart = DateTime\Timestamp::now();
        $rootDir = $this->tools->rootDir;
        $workspaceDir = $rootDir . '/workspace';
        $cacheDir = $rootDir . '/cache';

        $analyzers = Discovery::analyzers($rootDir, $this->filter->analyzer);
        if ($analyzers === []) {
            Output::error('No analyzers available. Run: bin/benchmark setup');
            return 1;
        }

        $projects = Discovery::projects($workspaceDir, $this->filter->project);
        if ($projects === []) {
            Output::error('No projects available. Run: bin/benchmark setup');
            return 1;
        }

        $timestamp = DateTime\Timestamp::now()->format('yyyyMMdd-HHmmss');
        $resultsDir = Str\format('%s/results/%s', $rootDir, $timestamp);
        Filesystem\create_directory($resultsDir);

        Output::blank();
        Output::title('PHP Static Analyzer Benchmarks');
        Output::blank();
        Output::configLine('Runs', (string) $this->runs);
        Output::configLine('Warmup', (string) $this->warmup);
        Output::configLine('Analyzers', Str\join(
            Vec\map($analyzers, static fn(AnalyzerTool $a): string => $a->getDisplayName()),
            ', ',
        ));
        Output::configLine('Projects', Str\join(
            Vec\map($projects, static fn(Project $p): string => $p->getDisplayName()),
            ', ',
        ));
        Output::configLine('Results', $resultsDir);
        Output::blank();

        EnvironmentCheck::warn($this->tools);
        SystemStability::warn();

        if ($this->skipStability) {
            Output::warn('Skipping system stability check (--skip-stability)');
        }

        if (!$this->skipStability && !SystemStability::check()) {
            Output::error('Aborting benchmarks due to unstable system.');
            Output::error('Use --skip-stability to bypass this check.');
            return 1;
        }

        $summary = new Summary($resultsDir);
        $summary->writeHeader($this->runs, $this->warmup);

        $results = new BenchmarkResults();
        $runner = new CategoryRunner($this->runs, $this->warmup);
        $category = $this->filter->category;

        $projectCount = Iter\count($projects);
        $projectIndex = 0;

        foreach ($projects as $project) {
            $projectIndex++;
            $ws = Str\format('%s/%s', $workspaceDir, $project->value);
            $projectResultsDir = Str\format('%s/%s', $resultsDir, $project->value);
            Filesystem\create_directory($projectResultsDir);

            Output::section($project->getDisplayName(), Str\format('[%d/%d]', $projectIndex, $projectCount));
            $summary->writeProjectHeading($project);

            $projectCtx = new ProjectContext($this->tools, $project, $ws, $cacheDir, $projectResultsDir);
            $ctx = new RunContext($analyzers, $projectCtx, $summary, $results);

            if ($category === null || $category === BenchmarkCategory::Uncached) {
                $runner->runUncached($ctx);
            }

            if ($category === null || $category === BenchmarkCategory::Cached) {
                $runner->runCached($ctx);
            }

            if ($category === null || $category === BenchmarkCategory::Uncached) {
                $runner->runMemory($ctx);
            }
        }

        $results->printFinalReport();
        $results->exportMarkdown($resultsDir . '/report.md');
        $results->exportJson($resultsDir . '/report.json');

        $elapsed = (string) DateTime\Timestamp::now()->since($overallStart);
        Output::success(Str\format('Results saved to %s', $resultsDir));
        Output::success(Str\format('Benchmarks complete (%s)', $elapsed));

        return 0;
    }
}
