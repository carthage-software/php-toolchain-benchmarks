<?php

declare(strict_types=1);

namespace CarthageSoftware\StaticAnalyzersBenchmark\Benchmark;

use CarthageSoftware\StaticAnalyzersBenchmark\Configuration\Analyzer;
use CarthageSoftware\StaticAnalyzersBenchmark\Configuration\IncrementalVariant;
use CarthageSoftware\StaticAnalyzersBenchmark\Result\BenchmarkResults;
use CarthageSoftware\StaticAnalyzersBenchmark\Result\HyperfineResult;
use CarthageSoftware\StaticAnalyzersBenchmark\Result\MemoryResult;
use CarthageSoftware\StaticAnalyzersBenchmark\Result\Summary;
use CarthageSoftware\StaticAnalyzersBenchmark\Support\Console;
use CarthageSoftware\StaticAnalyzersBenchmark\Support\ShellHelper;
use Closure;
use Psl\Filesystem;
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
     * @param list<Analyzer> $analyzers
     */
    public function runUncached(
        array $analyzers,
        ProjectContext $ctx,
        Summary $summary,
        BenchmarkResults $results,
    ): void {
        Console::heading(Str\format('Category: Uncached - %s', $ctx->project->getDisplayName()));

        $jsonFile = $ctx->resultsDir . '/uncached.json';
        $mdFile = $ctx->resultsDir . '/uncached.md';
        $args = $this->baseHyperfineArgs($jsonFile, $mdFile);

        foreach ($analyzers as $analyzer) {
            $cacheDir = $ctx->analyzerCacheDir($analyzer);
            $args[] = '--command-name';
            $args[] = $analyzer->getDisplayName();
            $args[] = '--prepare';
            $args[] = $analyzer->getClearCacheCommand($cacheDir);
            $args[] = $analyzer->getUncachedCommand($ctx->rootDir, $ctx->workspace, $ctx->configDir, $cacheDir);
        }

        Console::info('Measuring performance...');
        ShellHelper::runHyperfine($args);
        $hyperfine = ShellHelper::parseResults($jsonFile);

        $memoryResults = self::measureMemory(
            $analyzers,
            static function (Analyzer $a) use ($ctx): string {
                $cacheDir = $ctx->analyzerCacheDir($a);
                ShellHelper::exec($a->getClearCacheCommand($cacheDir));

                return $a->getUncachedCommand($ctx->rootDir, $ctx->workspace, $ctx->configDir, $cacheDir);
            },
            'uncached',
        );

        $summary->writeCategory('Uncached', $memoryResults, $mdFile);
        self::collectResults($results, $ctx, 'Uncached', $hyperfine, $memoryResults);
    }

    /**
     * @param list<Analyzer> $analyzers
     */
    public function runIncremental(
        array $analyzers,
        ProjectContext $ctx,
        Summary $summary,
        BenchmarkResults $results,
    ): void {
        Console::heading(Str\format(
            'Category: Incremental (Cache Invalidation) - %s',
            $ctx->project->getDisplayName(),
        ));

        $incFile = $ctx->project->getIncrementalFile($ctx->workspace);
        if (!Filesystem\exists($incFile)) {
            Console::warn(Str\format('Incremental file not found: %s, skipping', $incFile));
            return;
        }

        $summary->writeIncrementalHeader($incFile);

        foreach (IncrementalVariant::cases() as $variant) {
            Console::heading(Str\format('Incremental variant: %s', $variant->getLabel()));

            $modifyCmd = $variant->getModifyCommand($incFile);
            $jsonFile = Str\format('%s/incremental-%s.json', $ctx->resultsDir, $variant->value);
            $mdFile = Str\format('%s/incremental-%s.md', $ctx->resultsDir, $variant->value);

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

            foreach ($analyzers as $analyzer) {
                $cacheDir = $ctx->analyzerCacheDir($analyzer);
                $cmd = $analyzer->getCommand($ctx->rootDir, $ctx->workspace, $ctx->configDir, $cacheDir);
                $args[] = '--command-name';
                $args[] = $analyzer->getDisplayName();
                $args[] = '--prepare';

                $args[] = $variant === IncrementalVariant::NoChange
                    ? Str\format('%s >/dev/null 2>&1 || true', $cmd)
                    : Str\format(
                        'git -C %s checkout -- . 2>/dev/null; %s >/dev/null 2>&1 || true; %s',
                        $ctx->workspace,
                        $cmd,
                        $modifyCmd,
                    );

                $args[] = $cmd;
            }

            Console::info('Measuring performance...');
            ShellHelper::runHyperfine($args);
            $hyperfine = ShellHelper::parseResults($jsonFile);
            $summary->writeIncrementalVariant($variant, $mdFile);

            $category = Str\format('Incremental: %s', $variant->getLabel());
            self::collectResults($results, $ctx, $category, $hyperfine, null);

            if ($variant !== IncrementalVariant::NoChange) {
                ShellHelper::exec(Str\format('git -C %s checkout -- .', $ctx->workspace));
            }
        }
    }

    /**
     * @return list<string>
     */
    private function baseHyperfineArgs(string $jsonFile, string $mdFile): array
    {
        return [
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
    }

    /**
     * Measure peak memory for each analyzer using the given command builder.
     *
     * @param list<Analyzer> $analyzers
     * @param Closure(Analyzer): non-empty-string $getCommand
     * @param non-empty-string $label
     *
     * @return list<MemoryResult>
     */
    private static function measureMemory(array $analyzers, Closure $getCommand, string $label): array
    {
        Console::info(Str\format('Measuring memory (%s)...', $label));

        $results = [];
        foreach ($analyzers as $analyzer) {
            $cmd = $getCommand($analyzer);
            $results[] = MemoryResult::measure($analyzer->getDisplayName(), $cmd);
        }

        return $results;
    }

    /**
     * @param list<MemoryResult>|null $memoryResults
     */
    private static function collectResults(
        BenchmarkResults $results,
        ProjectContext $ctx,
        string $category,
        ?HyperfineResult $hyperfine,
        ?array $memoryResults,
    ): void {
        if ($hyperfine !== null) {
            Vec\map($hyperfine->results, static fn(array $r) => $results->addTiming($ctx->project, $category, $r));
        }

        if ($memoryResults !== null) {
            Vec\map($memoryResults, static fn(MemoryResult $m) => $results->addMemory(
                $ctx->project,
                $category,
                $m->analyzerName,
                $m->peakMemoryMb,
            ));
        }
    }
}
