<?php

declare(strict_types=1);

namespace CarthageSoftware\StaticAnalyzersBenchmark\Benchmark;

use CarthageSoftware\StaticAnalyzersBenchmark\Configuration\Analyzer;
use CarthageSoftware\StaticAnalyzersBenchmark\Configuration\Project;
use CarthageSoftware\StaticAnalyzersBenchmark\Configuration\ToolPaths;
use Psl\Str;

/**
 * Per-project state passed to category runners.
 */
final readonly class ProjectContext
{
    /** @var non-empty-string */
    public string $configDir;

    /**
     * @param non-empty-string $workspace  Path to the cloned project.
     * @param non-empty-string $cacheDir   Base cache directory.
     * @param non-empty-string $resultsDir Project-specific results directory.
     */
    public function __construct(
        public ToolPaths $tools,
        public Project $project,
        public string $workspace,
        public string $cacheDir,
        public string $resultsDir,
    ) {
        $this->configDir = $this->workspace . '/.bench-configs';
    }

    /**
     * @return non-empty-string
     */
    public function analyzerCacheDir(Analyzer $analyzer): string
    {
        return Str\format('%s/%s/%s', $this->cacheDir, $this->project->value, $analyzer->value);
    }
}
