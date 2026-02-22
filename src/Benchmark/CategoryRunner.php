<?php

declare(strict_types=1);

namespace CarthageSoftware\StaticAnalyzersBenchmark\Benchmark;

use CarthageSoftware\StaticAnalyzersBenchmark\Configuration\AnalyzerTool;
use CarthageSoftware\StaticAnalyzersBenchmark\Result\MemoryResult;
use CarthageSoftware\StaticAnalyzersBenchmark\Support\Output;
use CarthageSoftware\StaticAnalyzersBenchmark\Support\ShellHelper;
use CarthageSoftware\StaticAnalyzersBenchmark\Support\Spinner;
use Closure;
use Psl\Async;
use Psl\DateTime;
use Psl\Iter;
use Psl\Str;
use Psl\Vec;

final readonly class CategoryRunner
{
    /**
     * @param int<1, max> $runs
     * @param int<0, max> $warmup
     */
    public function __construct(
        private int $runs,
        private int $warmup,
    ) {}

    /**
     * Run the uncached benchmark type.
     */
    public function runUncached(RunContext $ctx): void
    {
        $built = $this->buildUncachedArgs($ctx);
        $this->runType('Uncached', $built, $ctx);
        $ctx->summary->writeBenchmarkType('Uncached', $built['mdFile']);
    }

    /**
     * Run the cached benchmark type (only analyzers that support caching).
     */
    public function runCached(RunContext $ctx): void
    {
        $cachingAnalyzers = Vec\filter($ctx->analyzers, static fn(AnalyzerTool $a): bool => $a->supportsCaching());
        if ($cachingAnalyzers === []) {
            return;
        }

        // Clear caches and warm them in one pass before measuring.
        Output::withSpinner('Cached — Warming caches...', static function () use ($ctx, $cachingAnalyzers): void {
            foreach ($cachingAnalyzers as $analyzer) {
                $cacheDir = $ctx->project->analyzerCacheDir($analyzer);
                ShellHelper::exec($analyzer->getClearCacheCommand($cacheDir));
                ShellHelper::exec(Str\format('%s >/dev/null 2>&1 || true', $analyzer->getCommand(
                    $ctx->project->tools,
                    $ctx->project->workspace,
                    $ctx->project->configDir($analyzer),
                    $cacheDir,
                )));
            }
        });

        $built = $this->buildCachedArgs($ctx, $cachingAnalyzers);
        $this->runType('Cached', $built, $ctx);
        $ctx->summary->writeBenchmarkType('Cached', $built['mdFile']);
    }

    /**
     * Measure peak memory for each analyzer (uncached, cold start).
     */
    public function runMemory(RunContext $ctx): void
    {
        $project = $ctx->project;
        $memoryResults = self::measureMemory($ctx->analyzers, static function (AnalyzerTool $a) use ($project): string {
            $cacheDir = $project->analyzerCacheDir($a);
            ShellHelper::exec($a->getClearCacheCommand($cacheDir));

            return $a->getUncachedCommand($project->tools, $project->workspace, $project->configDir($a), $cacheDir);
        });

        Vec\map($memoryResults, static fn(MemoryResult $m) => $ctx->results->addMemory(
            $ctx->project->project,
            'Uncached',
            $m->analyzerName,
            $m->peakMemoryMb,
        ));

        $ctx->summary->writeMemory($memoryResults);
    }

    /**
     * Run a single benchmark type via hyperfine with a live spinner.
     *
     * @param array{args: list<string>, jsonFile: non-empty-string, mdFile: non-empty-string} $built
     */
    private function runType(string $label, array $built, RunContext $ctx): void
    {
        Output::withSpinner(Str\format('%s — Measuring performance...', $label), static function () use ($built): void {
            ShellHelper::runHyperfine($built['args']);
        });

        $hyperfine = ShellHelper::parseResults($built['jsonFile']);
        if ($hyperfine === null) {
            return;
        }

        Vec\map($hyperfine->results, static fn(array $r) => $ctx->results->addTiming(
            $ctx->project->project,
            $label,
            $r,
        ));
    }

    /**
     * @return array{args: list<string>, jsonFile: non-empty-string, mdFile: non-empty-string}
     */
    private function buildUncachedArgs(RunContext $ctx): array
    {
        $jsonFile = $ctx->project->resultsDir . '/uncached.json';
        $mdFile = $ctx->project->resultsDir . '/uncached.md';
        $args = [
            '--runs',
            (string) $this->runs,
            '--warmup',
            (string) $this->warmup,
            '--style',
            'none',
            '--shell',
            'none',
            '--ignore-failure',
            '--export-json',
            $jsonFile,
            '--export-markdown',
            $mdFile,
        ];

        foreach ($ctx->analyzers as $analyzer) {
            $cacheDir = $ctx->project->analyzerCacheDir($analyzer);
            $args[] = '--command-name';
            $args[] = $analyzer->getDisplayName();
            $args[] = '--prepare';
            $args[] = self::shellWrap($analyzer->getClearCacheCommand($cacheDir));
            $args[] = $analyzer->getUncachedCommand(
                $ctx->project->tools,
                $ctx->project->workspace,
                $ctx->project->configDir($analyzer),
                $cacheDir,
            );
        }

        return ['args' => $args, 'jsonFile' => $jsonFile, 'mdFile' => $mdFile];
    }

    /**
     * @param list<AnalyzerTool> $cachingAnalyzers
     *
     * @return array{args: list<string>, jsonFile: non-empty-string, mdFile: non-empty-string}
     */
    private function buildCachedArgs(RunContext $ctx, array $cachingAnalyzers): array
    {
        $jsonFile = $ctx->project->resultsDir . '/cached.json';
        $mdFile = $ctx->project->resultsDir . '/cached.md';

        $args = [
            '--runs',
            (string) $this->runs,
            '--warmup',
            '0',
            '--style',
            'none',
            '--shell',
            'none',
            '--ignore-failure',
            '--export-json',
            $jsonFile,
            '--export-markdown',
            $mdFile,
        ];

        foreach ($cachingAnalyzers as $analyzer) {
            $cacheDir = $ctx->project->analyzerCacheDir($analyzer);
            $args[] = '--command-name';
            $args[] = $analyzer->getDisplayName();
            $args[] = $analyzer->getCommand(
                $ctx->project->tools,
                $ctx->project->workspace,
                $ctx->project->configDir($analyzer),
                $cacheDir,
            );
        }

        return ['args' => $args, 'jsonFile' => $jsonFile, 'mdFile' => $mdFile];
    }

    /**
     * Wrap a command string for sh execution under --shell=none.
     *
     * @param non-empty-string $command
     *
     * @return non-empty-string
     */
    private static function shellWrap(string $command): string
    {
        return Str\format('sh -c %s', \escapeshellarg($command));
    }

    /**
     * Measure peak memory for each analyzer with a live spinner.
     *
     * @param list<AnalyzerTool> $analyzers
     * @param Closure(AnalyzerTool): non-empty-string $getCommand
     *
     * @return list<MemoryResult>
     */
    private static function measureMemory(array $analyzers, Closure $getCommand): array
    {
        $results = [];
        $count = Iter\count($analyzers);
        $spinner = new Spinner('Measuring memory...', '    ');
        $index = 0;

        foreach ($analyzers as $analyzer) {
            $index++;
            $cmd = $getCommand($analyzer);
            $spinner->tick(Str\format('Measuring memory — %s (%d/%d)', $analyzer->getDisplayName(), $index, $count));

            $awaitable = Async\run(static fn(): MemoryResult => MemoryResult::measure(
                $analyzer->getDisplayName(),
                $cmd,
            ));

            while (!$awaitable->isComplete()) {
                Async\sleep(DateTime\Duration::milliseconds(80));
                $spinner->tick();
            }

            $results[] = $awaitable->await();
        }

        $spinner->succeed('Memory measured');

        return $results;
    }
}
