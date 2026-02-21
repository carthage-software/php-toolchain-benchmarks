<?php

declare(strict_types=1);

namespace CarthageSoftware\StaticAnalyzersBenchmark\Benchmark;

use CarthageSoftware\StaticAnalyzersBenchmark\Configuration\Analyzer;
use CarthageSoftware\StaticAnalyzersBenchmark\Configuration\BenchmarkCategory;
use CarthageSoftware\StaticAnalyzersBenchmark\Configuration\Project;

final readonly class BenchmarkFilter
{
    public function __construct(
        public ?Analyzer $analyzer = null,
        public ?Project $project = null,
        public ?BenchmarkCategory $category = null,
    ) {}
}
