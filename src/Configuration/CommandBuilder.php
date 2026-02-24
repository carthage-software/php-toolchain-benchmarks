<?php

declare(strict_types=1);

namespace CarthageSoftware\ToolChainBenchmarks\Configuration;

use Psl\Str;
use Psl\Vec;

/**
 * Builds shell commands for benchmark runs.
 *
 * Separated from Tool to keep the enum as a pure data type.
 */
final readonly class CommandBuilder
{
    /**
     * Build the command for a standard benchmark run.
     *
     * @param non-empty-string $workspace
     * @param non-empty-string $configDir
     *
     * @return non-empty-string
     */
    public static function build(
        ToolInstance $instance,
        ToolPaths $tools,
        Project $project,
        string $workspace,
        string $configDir,
    ): string {
        return match ($instance->tool) {
            Tool::MagoFmt => self::magoCommand($tools, $instance, $workspace, $configDir, 'fmt --check'),
            Tool::MagoLint => self::magoCommand($tools, $instance, $workspace, $configDir, 'lint'),
            Tool::MagoAnalyze => self::magoCommand(
                $tools,
                $instance,
                $workspace,
                $configDir,
                'analyze --reporting-format=emacs',
            ),
            Tool::PrettyPhp => self::prettyPhpCommand($tools, $instance, $project, $workspace),
            Tool::PhpCsFixer => 'env PHP_CS_FIXER_IGNORE_ENV=1 '
                . self::phpToolCommand($tools, $instance, $configDir, 'fix --dry-run --config='),
            Tool::Phpcs => self::phpToolCommand($tools, $instance, $configDir, '--standard='),
            Tool::PhpStan => self::phpToolCommand($tools, $instance, $configDir, 'analyse --configuration=')
                . ' --memory-limit=-1',
            Tool::Psalm => self::phpToolCommand($tools, $instance, $configDir, '--config=') . ' --show-info',
            Tool::Phan => self::phpToolCommand($tools, $instance, $configDir, '--config-file ') . ' --memory-limit -1',
        };
    }

    /**
     * Build the command for uncached (cold start) runs.
     * Only differs from build() for Psalm (adds --no-cache).
     *
     * @param non-empty-string $workspace
     * @param non-empty-string $configDir
     *
     * @return non-empty-string
     */
    public static function buildUncached(
        ToolInstance $instance,
        ToolPaths $tools,
        Project $project,
        string $workspace,
        string $configDir,
    ): string {
        $cmd = self::build($instance, $tools, $project, $workspace, $configDir);

        if ($instance->tool === Tool::Psalm) {
            return $cmd . ' --no-cache';
        }

        return $cmd;
    }

    /**
     * Command to clear this tool's cache directory.
     *
     * @param non-empty-string $cacheDir
     *
     * @return non-empty-string
     */
    public static function clearCache(ToolInstance $instance, string $cacheDir): string
    {
        if ($instance->supportsCaching()) {
            return Str\format('rm -rf %s/*', $cacheDir);
        }

        return 'true';
    }

    /**
     * @return non-empty-string
     */
    private static function magoCommand(
        ToolPaths $tools,
        ToolInstance $instance,
        string $workspace,
        string $configDir,
        string $subcommand,
    ): string {
        return Str\format(
            '%s --workspace %s --config %s/%s %s',
            $tools->magoBinaryFor($instance->installSlug),
            $workspace,
            $configDir,
            $instance->tool->getConfigFilename($instance->version) ?? 'mago.toml',
            $subcommand,
        );
    }

    /**
     * @return non-empty-string
     */
    private static function prettyPhpCommand(
        ToolPaths $tools,
        ToolInstance $instance,
        Project $project,
        string $workspace,
    ): string {
        return Str\format(
            '%s %s %s %s --check --psr12',
            $tools->phpBinary,
            ToolPaths::OPCACHE_FLAGS,
            $tools->toolBin($instance),
            Str\join(Vec\map($project->getSourcePaths(), static fn(string $p): string => Str\format(
                '%s/%s',
                $workspace,
                $p,
            )), ' '),
        );
    }

    /**
     * Build a command for a PHP-based tool with a config flag.
     *
     * @return non-empty-string
     */
    private static function phpToolCommand(
        ToolPaths $tools,
        ToolInstance $instance,
        string $configDir,
        string $configFlag,
    ): string {
        return Str\format(
            '%s %s %s %s%s/%s',
            $tools->phpBinary,
            ToolPaths::OPCACHE_FLAGS,
            $tools->toolBin($instance),
            $configFlag,
            $configDir,
            $instance->tool->getConfigFilename($instance->version) ?? '',
        );
    }
}
