<?php

declare(strict_types=1);

namespace CarthageSoftware\StaticAnalyzersBenchmark\Configuration;

use Psl\Str;

enum Analyzer: string
{
    case Mago = 'mago';
    case PHPStan = 'phpstan';
    case Psalm = 'psalm';
    case Phan = 'phan';

    public function supportsCaching(): bool
    {
        return match ($this) {
            self::Mago => false,
            self::PHPStan, self::Psalm, self::Phan => true,
        };
    }

    /**
     * Returns the config filename, version-aware for Psalm.
     *
     * @param non-empty-string $version
     *
     * @return non-empty-string
     */
    public function getConfigFilename(string $version): string
    {
        return match ($this) {
            self::Mago => 'mago.toml',
            self::PHPStan => 'phpstan.neon',
            self::Psalm => Str\format('psalm-v%s.xml', Str\before($version, '.') ?? $version),
            self::Phan => 'phan.php',
        };
    }

    /**
     * Build the default command (used for cached / warm runs).
     *
     * @param non-empty-string $projectDir
     * @param non-empty-string $configDir
     * @param non-empty-string $cacheDir
     *
     * @return non-empty-string
     */
    public function getCommand(
        ToolPaths $tools,
        AnalyzerTool $tool,
        string $projectDir,
        string $configDir,
        string $cacheDir,
    ): string {
        $bin = $tools->analyzerBin($tool);
        $config = $tool->getConfigFilename();

        return match ($this) {
            self::Mago => Str\format(
                '%s --workspace %s --config %s/%s analyze --reporting-format=emacs',
                $tools->magoBinaryFor($tool->slug),
                $projectDir,
                $configDir,
                $config,
            ),
            self::PHPStan => Str\format(
                '%s -dopcache.enable=1 -dopcache.enable_cli=1 %s analyse --configuration=%s/%s --memory-limit=-1',
                $tools->phpBinary,
                $bin,
                $configDir,
                $config,
            ),
            self::Psalm => Str\format(
                '%s -dopcache.enable=1 -dopcache.enable_cli=1 %s --config=%s/%s --show-info',
                $tools->phpBinary,
                $bin,
                $configDir,
                $config,
            ),
            self::Phan => Str\format(
                '%s -dopcache.enable=1 -dopcache.enable_cli=1 %s --config-file %s/%s --memory-limit 4G',
                $tools->phpBinary,
                $bin,
                $configDir,
                $config,
            ),
        };
    }

    /**
     * Build the command for uncached (cold start) runs.
     *
     * @param non-empty-string $projectDir
     * @param non-empty-string $configDir
     * @param non-empty-string $cacheDir
     *
     * @return non-empty-string
     */
    public function getUncachedCommand(
        ToolPaths $tools,
        AnalyzerTool $tool,
        string $projectDir,
        string $configDir,
        string $cacheDir,
    ): string {
        $bin = $tools->analyzerBin($tool);
        $config = $tool->getConfigFilename();

        return match ($this) {
            self::Psalm => Str\format(
                '%s -dopcache.enable=1 -dopcache.enable_cli=1 %s --config=%s/%s --show-info --no-cache',
                $tools->phpBinary,
                $bin,
                $configDir,
                $config,
            ),
            default => $this->getCommand($tools, $tool, $projectDir, $configDir, $cacheDir),
        };
    }

    /**
     * @param non-empty-string $cacheDir
     *
     * @return non-empty-string
     */
    public function getClearCacheCommand(string $cacheDir): string
    {
        return match ($this) {
            self::Mago => 'true',
            self::PHPStan, self::Psalm, self::Phan => Str\format('rm -rf %s/*', $cacheDir),
        };
    }
}
