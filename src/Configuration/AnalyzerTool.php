<?php

declare(strict_types=1);

namespace CarthageSoftware\StaticAnalyzersBenchmark\Configuration;

use Psl\Filesystem;
use Psl\Str;

/**
 * A specific version of an analyzer tool, e.g. "PHPStan 2.1.39".
 *
 * Combines the tool type (Analyzer enum) with a version string and
 * a filesystem slug used for isolated installation directories.
 */
final readonly class AnalyzerTool
{
    /**
     * @param non-empty-string $version
     * @param non-empty-string $slug    Directory name under tools/, e.g. "phpstan-2.1.39"
     */
    public function __construct(
        public Analyzer $analyzer,
        public string $version,
        public string $slug,
    ) {}

    /**
     * @return non-empty-string
     */
    public function getDisplayName(): string
    {
        return Str\format('%s %s', $this->analyzer->name, $this->version);
    }

    public function supportsCaching(): bool
    {
        return $this->analyzer->supportsCaching();
    }

    /**
     * @param non-empty-string $rootDir
     */
    public function isAvailable(string $rootDir): bool
    {
        return Filesystem\is_file(Str\format(
            '%s/tools/%s/vendor/bin/%s',
            $rootDir,
            $this->slug,
            $this->analyzer->value,
        ));
    }

    /**
     * @return non-empty-string
     */
    public function getConfigFilename(): string
    {
        return $this->analyzer->getConfigFilename($this->version);
    }

    /**
     * @param non-empty-string $projectDir
     * @param non-empty-string $configDir
     * @param non-empty-string $cacheDir
     *
     * @return non-empty-string
     */
    public function getCommand(ToolPaths $tools, string $projectDir, string $configDir, string $cacheDir): string
    {
        return $this->analyzer->getCommand($tools, $this, $projectDir, $configDir, $cacheDir);
    }

    /**
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
        return $this->analyzer->getUncachedCommand($tools, $this, $projectDir, $configDir, $cacheDir);
    }

    /**
     * @param non-empty-string $cacheDir
     *
     * @return non-empty-string
     */
    public function getClearCacheCommand(string $cacheDir): string
    {
        return $this->analyzer->getClearCacheCommand($cacheDir);
    }
}
