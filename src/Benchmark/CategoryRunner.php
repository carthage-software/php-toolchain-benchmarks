<?php

declare(strict_types=1);

namespace CarthageSoftware\StaticAnalyzersBenchmark\Benchmark;

use CarthageSoftware\StaticAnalyzersBenchmark\Configuration\Analyzer;
use CarthageSoftware\StaticAnalyzersBenchmark\Configuration\IncrementalVariant;
use CarthageSoftware\StaticAnalyzersBenchmark\Result\MemoryResult;
use CarthageSoftware\StaticAnalyzersBenchmark\Support\Output;
use CarthageSoftware\StaticAnalyzersBenchmark\Support\ShellHelper;
use CarthageSoftware\StaticAnalyzersBenchmark\Support\Spinner;
use Closure;
use Psl\Async;
use Psl\DateTime;
use Psl\Filesystem;
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
     * Run all incremental benchmark variants.
     */
    public function runIncremental(RunContext $ctx): void
    {
        $incFile = $ctx->project->project->getIncrementalFile($ctx->project->workspace);
        if (!Filesystem\exists($incFile)) {
            Output::warn(Str\format('Incremental file not found: %s, skipping', $incFile));
            return;
        }

        $ctx->summary->writeIncrementalHeader($incFile);

        foreach (IncrementalVariant::cases() as $variant) {
            $built = $this->buildVariantArgs($ctx, $variant, $incFile);
            $this->runType($variant->getLabel(), $built, $ctx);
            $ctx->summary->writeIncrementalVariant($variant, $built['mdFile']);

            if ($variant !== IncrementalVariant::NoChange) {
                ShellHelper::exec(Str\format('git -C %s checkout -- .', $ctx->project->workspace));
            }
        }
    }

    /**
     * Measure peak memory for each analyzer (uncached, cold start).
     */
    public function runMemory(RunContext $ctx): void
    {
        $project = $ctx->project;
        $memoryResults = self::measureMemory($ctx->analyzers, static function (Analyzer $a) use ($project): string {
            $cacheDir = $project->analyzerCacheDir($a);
            ShellHelper::exec($a->getClearCacheCommand($cacheDir));

            return $a->getUncachedCommand($project->rootDir, $project->workspace, $project->configDir, $cacheDir);
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
            $args[] = $analyzer->getClearCacheCommand($cacheDir);
            $args[] = $analyzer->getUncachedCommand(
                $ctx->project->rootDir,
                $ctx->project->workspace,
                $ctx->project->configDir,
                $cacheDir,
            );
        }

        return ['args' => $args, 'jsonFile' => $jsonFile, 'mdFile' => $mdFile];
    }

    /**
     * @param non-empty-string $incFile
     *
     * @return array{args: list<string>, jsonFile: non-empty-string, mdFile: non-empty-string}
     */
    private function buildVariantArgs(RunContext $ctx, IncrementalVariant $variant, string $incFile): array
    {
        $modifyCmd = $variant->getModifyCommand($incFile);
        $jsonFile = Str\format('%s/incremental-%s.json', $ctx->project->resultsDir, $variant->value);
        $mdFile = Str\format('%s/incremental-%s.md', $ctx->project->resultsDir, $variant->value);

        $args = [
            '--runs',
            (string) $this->runs,
            '--warmup',
            '0',
            '--style',
            'none',
            '--ignore-failure',
            '--export-json',
            $jsonFile,
            '--export-markdown',
            $mdFile,
        ];

        foreach ($ctx->analyzers as $analyzer) {
            $cacheDir = $ctx->project->analyzerCacheDir($analyzer);
            $cmd = $analyzer->getCommand(
                $ctx->project->rootDir,
                $ctx->project->workspace,
                $ctx->project->configDir,
                $cacheDir,
            );
            $args[] = '--command-name';
            $args[] = $analyzer->getDisplayName();
            $args[] = '--prepare';

            $args[] = $variant === IncrementalVariant::NoChange
                ? Str\format('%s >/dev/null 2>&1 || true', $cmd)
                : Str\format(
                    'git -C %s checkout -- . 2>/dev/null; %s >/dev/null 2>&1 || true; %s',
                    $ctx->project->workspace,
                    $cmd,
                    $modifyCmd,
                );

            $args[] = $cmd;
        }

        return ['args' => $args, 'jsonFile' => $jsonFile, 'mdFile' => $mdFile];
    }

    /**
     * Measure peak memory for each analyzer with a live spinner.
     *
     * @param list<Analyzer> $analyzers
     * @param Closure(Analyzer): non-empty-string $getCommand
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
