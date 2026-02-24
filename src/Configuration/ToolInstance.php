<?php

declare(strict_types=1);

namespace CarthageSoftware\ToolChainBenchmarks\Configuration;

use Psl\Filesystem;
use Psl\Str;

/**
 * A specific version of a tool, e.g. "Mago Fmt 1.10.0" or "PHPStan 2.1.39".
 *
 * Combines the tool type (Tool enum) with a version string, a unique slug,
 * and the install slug (shared for all Mago tools of the same version).
 */
final readonly class ToolInstance
{
    /**
     * @param non-empty-string $version     e.g. "1.10.0"
     * @param non-empty-string $slug        Unique per tool+version, e.g. "mago-fmt-1.10.0"
     * @param non-empty-string $installSlug Package install dir, e.g. "mago-1.10.0"
     */
    public function __construct(
        public Tool $tool,
        public string $version,
        public string $slug,
        public string $installSlug,
    ) {}

    /**
     * @return non-empty-string
     */
    public function getDisplayName(): string
    {
        return Str\format('%s %s', $this->tool->getDisplayPrefix(), $this->version);
    }

    public function supportsCaching(): bool
    {
        return $this->tool->supportsCaching();
    }

    /**
     * @return non-empty-string|null
     */
    public function getConfigFilename(): ?string
    {
        return $this->tool->getConfigFilename($this->version);
    }

    /**
     * @param non-empty-string $rootDir
     */
    public function isAvailable(string $rootDir): bool
    {
        if ($this->tool->isNative()) {
            $tools = ToolPaths::resolve($rootDir);

            return Filesystem\is_file($tools->magoBinaryFor($this->installSlug));
        }

        return Filesystem\is_file(Str\format(
            '%s/tools/%s/vendor/bin/%s',
            $rootDir,
            $this->installSlug,
            $this->tool->getPackageName(),
        ));
    }

    /**
     * @param non-empty-string $workspace
     * @param non-empty-string $configDir
     *
     * @return non-empty-string
     */
    public function getCommand(ToolPaths $tools, Project $project, string $workspace, string $configDir): string
    {
        return CommandBuilder::build($this, $tools, $project, $workspace, $configDir);
    }

    /**
     * @param non-empty-string $workspace
     * @param non-empty-string $configDir
     *
     * @return non-empty-string
     */
    public function getUncachedCommand(ToolPaths $tools, Project $project, string $workspace, string $configDir): string
    {
        return CommandBuilder::buildUncached($this, $tools, $project, $workspace, $configDir);
    }

    /**
     * @param non-empty-string $cacheDir
     *
     * @return non-empty-string
     */
    public function getClearCacheCommand(string $cacheDir): string
    {
        return CommandBuilder::clearCache($this, $cacheDir);
    }
}
