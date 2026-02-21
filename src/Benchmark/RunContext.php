<?php

declare(strict_types=1);

namespace CarthageSoftware\StaticAnalyzersBenchmark\Benchmark;

use CarthageSoftware\StaticAnalyzersBenchmark\Configuration\Analyzer;
use CarthageSoftware\StaticAnalyzersBenchmark\Result\BenchmarkResults;
use CarthageSoftware\StaticAnalyzersBenchmark\Result\Summary;

/**
 * Groups the shared dependencies for a benchmark run against a single project.
 */
final readonly class RunContext
{
    /**
     * @param list<Analyzer> $analyzers
     */
    public function __construct(
        public array $analyzers,
        public ProjectContext $project,
        public Summary $summary,
        public BenchmarkResults $results,
    ) {}
}
