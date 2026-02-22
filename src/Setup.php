<?php

declare(strict_types=1);

namespace CarthageSoftware\StaticAnalyzersBenchmark;

use CarthageSoftware\StaticAnalyzersBenchmark\Configuration\Config;
use CarthageSoftware\StaticAnalyzersBenchmark\Configuration\Project;
use CarthageSoftware\StaticAnalyzersBenchmark\Support\Output;
use Psl\Async;
use Psl\Filesystem;
use Psl\Iter;
use Psl\Shell;
use Psl\Str;

final readonly class Setup
{
    /**
     * Run the full setup: check prerequisites, install deps, clone projects, process configs.
     *
     * @param non-empty-string $rootDir
     */
    public static function run(string $rootDir): int
    {
        $workspaceDir = $rootDir . '/workspace';
        $cacheDir = $rootDir . '/cache';

        Output::section('PHP Static Analyzer Benchmarks - Setup');

        if (!self::checkPrerequisites()) {
            return 1;
        }

        if (!self::installDependencies($rootDir)) {
            return 1;
        }

        if (!ToolInstaller::install($rootDir)) {
            return 1;
        }

        Filesystem\create_directory($workspaceDir);
        Filesystem\create_directory($cacheDir);

        $tasks = [];
        foreach (Project::cases() as $project) {
            $tasks[] = static fn(): int => self::setupProject($rootDir, $project, $workspaceDir, $cacheDir);
        }

        $results = Async\concurrently($tasks);
        if (Iter\any($results, static fn(int $r): bool => $r !== 0)) {
            return 1;
        }

        Output::section('Setup complete');
        Output::info('Run: bin/benchmark run');

        return 0;
    }

    private static function checkPrerequisites(): bool
    {
        return Iter\all(['php', 'composer', 'hyperfine'], static function (string $cmd): bool {
            $found = self::commandExists($cmd);
            $found
                ? Output::success(Str\format('%s found', $cmd))
                : Output::error(Str\format('%s is not installed', $cmd));

            return $found;
        });
    }

    /**
     * @param non-empty-string $rootDir
     */
    private static function installDependencies(string $rootDir): bool
    {
        Output::section('Installing composer dependencies');
        try {
            Shell\execute('composer', ['install', '--no-interaction'], $rootDir);
            Output::success('Composer dependencies installed');
            return true;
        } catch (Shell\Exception\FailedExecutionException $e) {
            Output::error(Str\format('Composer install failed: %s', $e->getErrorOutput()));
            return false;
        }
    }

    /**
     * @param non-empty-string $rootDir
     * @param non-empty-string $workspaceDir
     * @param non-empty-string $cacheDir
     */
    private static function setupProject(string $rootDir, Project $project, string $workspaceDir, string $cacheDir): int
    {
        Output::section(Str\format('Setting up project: %s', $project->getDisplayName()));

        $ws = Str\format('%s/%s', $workspaceDir, $project->value);

        if (!self::cloneOrUpdateProject($project, $ws)) {
            return 1;
        }

        Output::info('Running project setup...');
        try {
            Shell\execute('sh', ['-c', $project->getSetupCommand()], $ws);
        } catch (Shell\Exception\FailedExecutionException $e) {
            Output::error(Str\format('Project setup failed: %s', $e->getErrorOutput()));
            return 1;
        }

        self::processConfigs($rootDir, $project, $ws, $cacheDir);
        Output::success(Str\format('Project %s is ready', $project->getDisplayName()));

        return 0;
    }

    /**
     * @param non-empty-string $rootDir
     * @param non-empty-string $ws
     * @param non-empty-string $cacheDir
     */
    private static function processConfigs(string $rootDir, Project $project, string $ws, string $cacheDir): void
    {
        foreach (ToolInstaller::allTools() as $tool) {
            $analyzerCacheDir = Str\format('%s/%s/%s', $cacheDir, $project->value, $tool->slug);
            Filesystem\create_directory($analyzerCacheDir);

            $configFilename = $tool->getConfigFilename();
            $templateFile = Str\format('%s/project-configurations/%s/%s', $rootDir, $project->value, $configFilename);
            if (!Filesystem\exists($templateFile)) {
                continue;
            }

            $configOutput = Str\format('%s/.bench-configs/%s', $ws, $tool->slug);
            Filesystem\create_directory($configOutput);
            $outputFile = Str\format('%s/%s', $configOutput, $configFilename);
            Config::processTemplate($templateFile, $outputFile, $ws, $analyzerCacheDir);
            Output::success(Str\format('Processed %s for %s (%s)', $configFilename, $project->value, $tool->slug));
        }
    }

    /**
     * @param non-empty-string $ws
     */
    private static function cloneOrUpdateProject(Project $project, string $ws): bool
    {
        if (Filesystem\exists($ws . '/.git')) {
            Output::info('Repository already cloned, updating...');
            try {
                Shell\execute('git', ['fetch', 'origin'], $ws);
                Shell\execute('git', ['checkout', $project->getRef()], $ws);
                Shell\execute('git', ['pull', 'origin', $project->getRef()], $ws);
            } catch (Shell\Exception\FailedExecutionException) {
                Output::warn('Git pull failed, continuing with existing checkout');
            }
            return true;
        }

        Output::info(Str\format('Cloning %s (ref: %s)...', $project->getRepo(), $project->getRef()));
        try {
            Shell\execute('git', [
                'clone',
                '--branch',
                $project->getRef(),
                '--depth',
                '1',
                $project->getRepo(),
                $ws,
            ]);
            return true;
        } catch (Shell\Exception\FailedExecutionException $e) {
            Output::error(Str\format('Git clone failed: %s', $e->getErrorOutput()));
            return false;
        }
    }

    private static function commandExists(string $command): bool
    {
        try {
            Shell\execute('which', [$command]);
            return true;
        } catch (Shell\Exception\FailedExecutionException) {
            return false;
        }
    }
}
