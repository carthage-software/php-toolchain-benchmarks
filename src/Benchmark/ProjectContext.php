<?php

declare(strict_types=1);

namespace CarthageSoftware\ToolChainBenchmarks\Benchmark;

use CarthageSoftware\ToolChainBenchmarks\Configuration\Project;
use CarthageSoftware\ToolChainBenchmarks\Configuration\ToolInstance;
use CarthageSoftware\ToolChainBenchmarks\Configuration\ToolPaths;
use Psl\Str;

/**
 * Per-project state passed to runners.
 */
final readonly class ProjectContext
{
    /**
     * @param non-empty-string $workspace Path to the cloned project.
     * @param non-empty-string $cacheDir  Base cache directory.
     */
    public function __construct(
        public ToolPaths $tools,
        public Project $project,
        public string $workspace,
        public string $cacheDir,
    ) {}

    /**
     * Config directory for a tool instance.
     * Uses installSlug so all Mago tools of the same version share one config.
     *
     * @return non-empty-string
     */
    public function configDir(ToolInstance $tool): string
    {
        return Str\format('%s/.bench-configs/%s', $this->workspace, $tool->installSlug);
    }

    /**
     * @return non-empty-string
     */
    public function toolCacheDir(ToolInstance $tool): string
    {
        return Str\format('%s/%s/%s', $this->cacheDir, $this->project->value, $tool->installSlug);
    }
}
