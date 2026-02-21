<?php

declare(strict_types=1);

namespace CarthageSoftware\StaticAnalyzersBenchmark\Configuration;

use Psl\Filesystem;
use Psl\Str;

enum Analyzer: string
{
    case Mago = 'mago';
    case PHPStan = 'phpstan';
    case Psalm = 'psalm';
    case Phan = 'phan';

    /**
     * @return non-empty-string
     */
    public function getDisplayName(): string
    {
        return $this->name;
    }

    public function supportsCaching(): bool
    {
        return match ($this) {
            self::Mago => false,
            self::PHPStan, self::Psalm, self::Phan => true,
        };
    }

    /**
     * @param non-empty-string $rootDir
     */
    public function isAvailable(string $rootDir): bool
    {
        return Filesystem\is_file(Str\format('%s/tools/%s/vendor/bin/%s', $rootDir, $this->value, $this->value));
    }

    /**
     * Returns the config filename used in project-configurations/<name>/ and .bench-configs/.
     *
     * @return non-empty-string
     */
    public function getConfigFilename(): string
    {
        return match ($this) {
            self::Mago => 'mago.toml',
            self::PHPStan => 'phpstan.neon',
            self::Psalm => 'psalm.xml',
            self::Phan => 'phan.php',
        };
    }

    /**
     * Build the default command (used for cached / warm runs).
     *
     * @param non-empty-string $projectDir   Cloned project workspace
     * @param non-empty-string $configDir    .bench-configs directory
     * @param non-empty-string $cacheDir     Cache directory for this analyzer+project
     *
     * @return non-empty-string
     */
    public function getCommand(ToolPaths $tools, string $projectDir, string $configDir, string $cacheDir): string
    {
        $bin = $tools->analyzerBin($this);

        return match ($this) {
            self::Mago => Str\format(
                '%s --workspace %s --config %s/mago.toml analyze --reporting-format=emacs',
                $tools->magoBinary,
                $projectDir,
                $configDir,
            ),
            self::PHPStan => Str\format(
                '%s -dopcache.enable=1 -dopcache.enable_cli=1 %s analyse --configuration=%s/phpstan.neon --memory-limit=-1',
                $tools->phpBinary,
                $bin,
                $configDir,
            ),
            self::Psalm => Str\format(
                '%s -dopcache.enable=1 -dopcache.enable_cli=1 %s --config=%s/psalm.xml --show-info',
                $tools->phpBinary,
                $bin,
                $configDir,
            ),
            self::Phan => Str\format(
                '%s -dopcache.enable=1 -dopcache.enable_cli=1 %s --config-file %s/phan.php --memory-limit 4G',
                $tools->phpBinary,
                $bin,
                $configDir,
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
        string $projectDir,
        string $configDir,
        string $cacheDir,
    ): string {
        $bin = $tools->analyzerBin($this);

        return match ($this) {
            self::Psalm => Str\format(
                '%s -dopcache.enable=1 -dopcache.enable_cli=1 %s --config=%s/psalm.xml --show-info --no-cache',
                $tools->phpBinary,
                $bin,
                $configDir,
            ),
            default => $this->getCommand($tools, $projectDir, $configDir, $cacheDir),
        };
    }

    /**
     * Returns the shell command to clear this analyzer's cache.
     *
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
