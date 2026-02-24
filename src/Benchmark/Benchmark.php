<?php

declare(strict_types=1);

namespace CarthageSoftware\ToolChainBenchmarks\Benchmark;

use CarthageSoftware\ToolChainBenchmarks\Configuration\Project;
use CarthageSoftware\ToolChainBenchmarks\Configuration\ToolInstance;
use CarthageSoftware\ToolChainBenchmarks\Configuration\ToolKind;
use CarthageSoftware\ToolChainBenchmarks\Configuration\ToolPaths;
use CarthageSoftware\ToolChainBenchmarks\Result\Results;
use CarthageSoftware\ToolChainBenchmarks\Result\ResultsExporter;
use CarthageSoftware\ToolChainBenchmarks\Support\Output;
use Psl\DateTime;
use Psl\DateTime\Duration;
use Psl\Filesystem;
use Psl\Iter;
use Psl\Str;
use Psl\Vec;

final readonly class Benchmark
{
    /**
     * @param int<1, max> $runs
     */
    public function __construct(
        private ToolPaths $tools,
        private int $runs = 10,
        private ?Duration $timeout = null,
        private bool $skipStability = false,
        private BenchmarkFilter $filter = new BenchmarkFilter(),
    ) {}

    public function execute(): int
    {
        $overallStart = DateTime\Timestamp::now();
        $rootDir = $this->tools->rootDir;
        $workspaceDir = $rootDir . '/workspace';
        $cacheDir = $rootDir . '/cache';

        $allTools = Discovery::tools($rootDir, $this->filter->kind, $this->filter->tool);
        if ($allTools === []) {
            Output::error('No tools available. Run: ./src/main.php setup');
            return 1;
        }

        $projects = Discovery::projects($workspaceDir, $this->filter->project);
        if ($projects === []) {
            Output::error('No projects available. Run: ./src/main.php setup');
            return 1;
        }

        $timestamp = DateTime\Timestamp::now()->format('yyyyMMdd-HHmmss');
        $resultsDir = Str\format('%s/results/%s', $rootDir, $timestamp);
        Filesystem\create_directory($resultsDir);

        Output::blank();
        Output::title('PHP Toolchain Benchmarks');
        Output::blank();
        Output::configLine('Runs', (string) $this->runs);
        Output::configLine('Tools', Str\join(
            Vec\map($allTools, static fn(ToolInstance $t): string => $t->getDisplayName()),
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

        $results = new Results();
        $runner = new Runner($this->runs, $this->timeout);

        $projectCount = Iter\count($projects);
        $projectIndex = 0;

        foreach ($projects as $project) {
            $projectIndex++;
            $ws = Str\format('%s/%s', $workspaceDir, $project->value);

            Output::section($project->getDisplayName(), Str\format('[%d/%d]', $projectIndex, $projectCount));

            $projectCtx = new ProjectContext($this->tools, $project, $ws, $cacheDir);

            foreach (ToolKind::cases() as $kind) {
                $kindTools = Vec\filter($allTools, static fn(ToolInstance $t): bool => $t->tool->getKind() === $kind);
                if ($kindTools === []) {
                    continue;
                }

                Output::info(Str\format('%s', $kind->getDisplayName()));
                $ctx = new RunContext($kindTools, $projectCtx, $results);

                match ($kind) {
                    ToolKind::Formatter => self::runFormatterBenchmarks($runner, $ctx),
                    ToolKind::Linter => self::runLinterBenchmarks($runner, $ctx),
                    ToolKind::Analyzer => self::runAnalyzerBenchmarks($runner, $ctx),
                };
            }
        }

        $exporter = new ResultsExporter($results);
        $exporter->printFinalReport();
        $exporter->exportJson($resultsDir . '/report.json');

        $elapsed = (string) DateTime\Timestamp::now()->since($overallStart);
        Output::success(Str\format('Results saved to %s', $resultsDir));
        Output::success(Str\format('Benchmarks complete (%s)', $elapsed));

        return 0;
    }

    private static function runFormatterBenchmarks(Runner $runner, RunContext $ctx): void
    {
        $runner->runBenchmark('Formatter', $ctx);
    }

    private static function runLinterBenchmarks(Runner $runner, RunContext $ctx): void
    {
        $runner->runBenchmark('Linter', $ctx);
    }

    private static function runAnalyzerBenchmarks(Runner $runner, RunContext $ctx): void
    {
        $runner->runUncached($ctx);
        $runner->runCached($ctx);
    }
}
