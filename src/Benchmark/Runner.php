<?php

declare(strict_types=1);

namespace CarthageSoftware\ToolChainBenchmarks\Benchmark;

use CarthageSoftware\ToolChainBenchmarks\Configuration\ToolInstance;
use CarthageSoftware\ToolChainBenchmarks\Profiler\CommandProfiler;
use CarthageSoftware\ToolChainBenchmarks\Profiler\Profile\CommandProfile;
use CarthageSoftware\ToolChainBenchmarks\Profiler\ProfileFailure;
use CarthageSoftware\ToolChainBenchmarks\Support\Output;
use CarthageSoftware\ToolChainBenchmarks\Support\ShellHelper;
use Psl\DateTime\Duration;
use Psl\Str;
use Psl\Vec;

final readonly class Runner
{
    /**
     * @param int<1, max> $runs
     */
    public function __construct(
        private int $runs,
        private ?Duration $timeout = null,
    ) {}

    /**
     * Run a benchmark for formatters or linters.
     *
     * Profiles each tool individually, collecting both timing and memory in one shot.
     */
    public function runBenchmark(string $label, RunContext $ctx): void
    {
        foreach ($ctx->tools as $tool) {
            $command = $tool->getCommand(
                $ctx->project->tools,
                $ctx->project->project,
                $ctx->project->workspace,
                $ctx->project->configDir($tool),
            );

            $result = $this->profileTool($label, $tool, $command);
            $this->handleResult($result, $ctx, $label, $tool);
        }
    }

    /**
     * Run the uncached benchmark for analyzers.
     *
     * Clears cache before each run via prepare command.
     */
    public function runUncached(RunContext $ctx): void
    {
        foreach ($ctx->tools as $tool) {
            $cacheDir = $ctx->project->toolCacheDir($tool);
            $command = $tool->getUncachedCommand(
                $ctx->project->tools,
                $ctx->project->project,
                $ctx->project->workspace,
                $ctx->project->configDir($tool),
            );

            $profiler = $this->createProfiler();
            $profiler->setPrepareCommand($tool->getClearCacheCommand($cacheDir));

            $result = Output::withSpinner(
                Str\format('Uncached — %s', $tool->getDisplayName()),
                static fn(): CommandProfile|ProfileFailure => $profiler->profile($command),
            );

            if ($result instanceof ProfileFailure) {
                Output::warn(Str\format('Skipped %s: %s', $tool->getDisplayName(), $result->reason));
            }

            $this->handleResult($result, $ctx, 'Cold', $tool);
        }
    }

    /**
     * Run the cached benchmark (only tools that support caching).
     *
     * Warms caches first, then benchmarks.
     */
    public function runCached(RunContext $ctx): void
    {
        $cachingTools = Vec\filter($ctx->tools, static fn(ToolInstance $t): bool => $t->supportsCaching());
        if ($cachingTools === []) {
            return;
        }

        // Warm caches: clear, then run once to populate
        Output::withSpinner('Cached — Warming caches...', static function () use ($ctx, $cachingTools): void {
            foreach ($cachingTools as $tool) {
                $cacheDir = $ctx->project->toolCacheDir($tool);
                ShellHelper::exec($tool->getClearCacheCommand($cacheDir));
                ShellHelper::exec(Str\format('%s >/dev/null 2>&1 || true', $tool->getCommand(
                    $ctx->project->tools,
                    $ctx->project->project,
                    $ctx->project->workspace,
                    $ctx->project->configDir($tool),
                )));
            }
        });

        foreach ($cachingTools as $tool) {
            $command = $tool->getCommand(
                $ctx->project->tools,
                $ctx->project->project,
                $ctx->project->workspace,
                $ctx->project->configDir($tool),
            );

            $result = $this->profileTool('Hot', $tool, $command);
            $this->handleResult($result, $ctx, 'Hot', $tool);
        }
    }

    /**
     * Profile a single tool with spinner feedback.
     *
     * @param non-empty-string $command
     */
    private function profileTool(string $label, ToolInstance $tool, string $command): CommandProfile|ProfileFailure
    {
        $profiler = $this->createProfiler();

        $result = Output::withSpinner(
            Str\format('%s — %s', $label, $tool->getDisplayName()),
            static fn(): CommandProfile|ProfileFailure => $profiler->profile($command),
        );

        if ($result instanceof ProfileFailure) {
            Output::warn(Str\format('Skipped %s: %s', $tool->getDisplayName(), $result->reason));
        }

        return $result;
    }

    /**
     * Record a profiling result: on success add to results, on timeout record the timeout.
     */
    private function handleResult(
        CommandProfile|ProfileFailure $result,
        RunContext $ctx,
        string $category,
        ToolInstance $tool,
    ): void {
        if ($result instanceof CommandProfile) {
            $ctx->results->addResult($ctx->project->project, $category, $tool->getDisplayName(), $result);

            return;
        }

        if (Str\contains($result->reason, 'timed out')) {
            $ctx->results->addTimedOut($ctx->project->project, $category, $tool->getDisplayName());
        }
    }

    private function createProfiler(): CommandProfiler
    {
        $runs = $this->runs + 1;

        return new CommandProfiler(runs: $runs, timeout: $this->timeout ?? Duration::minutes(5));
    }
}
