<?php

declare(strict_types=1);

namespace CarthageSoftware\StaticAnalyzersBenchmark\Benchmark;

use CarthageSoftware\StaticAnalyzersBenchmark\Configuration\Analyzer;
use CarthageSoftware\StaticAnalyzersBenchmark\Configuration\Project;
use CarthageSoftware\StaticAnalyzersBenchmark\Support\Console;
use Psl\Filesystem;
use Psl\Str;

final readonly class Discovery
{
    /**
     * @param non-empty-string $rootDir
     *
     * @return list<Analyzer>
     */
    public static function analyzers(string $rootDir, ?Analyzer $filter): array
    {
        $analyzers = [];
        foreach (Analyzer::cases() as $analyzer) {
            if ($filter !== null && $analyzer !== $filter) {
                continue;
            }

            if (!$analyzer->isAvailable($rootDir)) {
                Console::warn(Str\format('%s is not available, skipping', $analyzer->getDisplayName()));
                continue;
            }

            $analyzers[] = $analyzer;
            Console::success(Str\format(
                'Found analyzer: %s (cache: %s)',
                $analyzer->getDisplayName(),
                $analyzer->supportsCaching() ? 'yes' : 'no',
            ));
        }

        return $analyzers;
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
                Console::warn(Str\format('Project %s not set up (workspace missing), skipping', $project->value));
                continue;
            }

            $projects[] = $project;
            Console::success(Str\format('Found project: %s', $project->getDisplayName()));
        }

        return $projects;
    }
}
