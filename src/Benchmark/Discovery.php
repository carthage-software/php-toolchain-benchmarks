<?php

declare(strict_types=1);

namespace CarthageSoftware\ToolChainBenchmarks\Benchmark;

use CarthageSoftware\ToolChainBenchmarks\Configuration\Project;
use CarthageSoftware\ToolChainBenchmarks\Configuration\Tool;
use CarthageSoftware\ToolChainBenchmarks\Configuration\ToolInstance;
use CarthageSoftware\ToolChainBenchmarks\Configuration\ToolKind;
use CarthageSoftware\ToolChainBenchmarks\Setup\ToolInstaller;
use CarthageSoftware\ToolChainBenchmarks\Support\Output;
use Psl\Filesystem;
use Psl\Str;

final readonly class Discovery
{
    /**
     * @param non-empty-string $rootDir
     *
     * @return list<ToolInstance>
     */
    public static function tools(string $rootDir, ?ToolKind $kindFilter, ?Tool $toolFilter): array
    {
        $tools = [];
        foreach (ToolInstaller::allTools() as $instance) {
            if ($kindFilter !== null && $instance->tool->getKind() !== $kindFilter) {
                continue;
            }

            if ($toolFilter !== null && $instance->tool !== $toolFilter) {
                continue;
            }

            if (!$instance->isAvailable($rootDir)) {
                Output::warn(Str\format('%s is not available, skipping', $instance->getDisplayName()));
                continue;
            }

            $tools[] = $instance;
            Output::success(Str\format('Found %s: %s', $instance->tool->getKind()->value, $instance->getDisplayName()));
        }

        return $tools;
    }

    /**
     * @param non-empty-string $workspaceDir
     *
     * @return list<Project>
     */
    public static function projects(string $workspaceDir, ?Project $filter): array
    {
        $projects = [];
        foreach (Project::cases() as $project) {
            if ($filter !== null && $project !== $filter) {
                continue;
            }

            $ws = Str\format('%s/%s', $workspaceDir, $project->value);
            if (!Filesystem\exists($ws)) {
                Output::warn(Str\format('Project %s not set up (workspace missing), skipping', $project->value));
                continue;
            }

            $projects[] = $project;
            Output::success(Str\format('Found project: %s', $project->getDisplayName()));
        }

        return $projects;
    }
}
