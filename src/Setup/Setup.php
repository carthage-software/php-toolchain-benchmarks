<?php

declare(strict_types=1);

namespace CarthageSoftware\ToolChainBenchmarks\Setup;

use CarthageSoftware\ToolChainBenchmarks\Configuration\Config;
use CarthageSoftware\ToolChainBenchmarks\Configuration\Project;
use CarthageSoftware\ToolChainBenchmarks\Support\Output;
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

        Output::section('PHP Toolchain Benchmarks - Setup');

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
        Output::info('Run: ./src/main.php run');

        return 0;
    }

    private static function checkPrerequisites(): bool
    {
        $allFound = true;
        foreach (['php', 'composer'] as $cmd) {
            if (!self::commandExists($cmd)) {
                Output::error(Str\format('%s is not installed', $cmd));
                $allFound = false;
                continue;
            }

            Output::success(Str\format('%s found', $cmd));
        }

        return $allFound;
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

        if (!ProjectCloner::cloneOrUpdate($project, $ws)) {
            return 1;
        }

        if (!ProjectCloner::runSetup($project, $ws)) {
            return 1;
        }

        self::processConfigs($rootDir, $project, $ws, $cacheDir);
        Output::success(Str\format('Project %s is ready', $project->getDisplayName()));

        return 0;
    }

    /**
     * Process config templates for all tools. Deduplicates by installSlug+config to avoid
     * processing mago.toml three times for the same Mago version.
     *
     * @param non-empty-string $rootDir
     * @param non-empty-string $ws
     * @param non-empty-string $cacheDir
     */
    private static function processConfigs(string $rootDir, Project $project, string $ws, string $cacheDir): void
    {
        $processed = [];
        foreach (ToolInstaller::allTools() as $tool) {
            $configFilename = $tool->getConfigFilename();
            if ($configFilename === null) {
                continue;
            }

            $dedupeKey = $tool->installSlug . ':' . $configFilename;
            if (Iter\contains_key($processed, $dedupeKey)) {
                continue;
            }
            $processed[$dedupeKey] = true;

            $installSlug = $tool->installSlug;
            $toolCacheDir = Str\format('%s/%s/%s', $cacheDir, $project->value, $installSlug);
            Filesystem\create_directory($toolCacheDir);

            $templateFile = Str\format('%s/project-configurations/%s/%s', $rootDir, $project->value, $configFilename);
            if (!Filesystem\exists($templateFile)) {
                continue;
            }

            $configOutput = Str\format('%s/.bench-configs/%s', $ws, $installSlug);
            Filesystem\create_directory($configOutput);
            Config::processTemplate(
                $templateFile,
                Str\format('%s/%s', $configOutput, $configFilename),
                $ws,
                $toolCacheDir,
            );
            Output::success(Str\format('Processed %s for %s (%s)', $configFilename, $project->value, $installSlug));
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
