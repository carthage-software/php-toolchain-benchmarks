<?php

declare(strict_types=1);

namespace CarthageSoftware\ToolChainBenchmarks\Configuration;

use Psl\Str;

/**
 * Resolved binary paths for all tools used in benchmark runs.
 */
final readonly class ToolPaths
{
    public const string OPCACHE_FLAGS = '-dopcache.enable=1 -dopcache.enable_cli=1 -dmemory_limit=-1';

    /**
     * @param non-empty-string $rootDir
     * @param non-empty-string $phpBinary
     */
    public function __construct(
        public string $rootDir,
        public string $phpBinary,
    ) {}

    /**
     * @param non-empty-string $rootDir
     */
    public static function resolve(string $rootDir, ?string $phpBinary = null): self
    {
        return new self($rootDir, $phpBinary !== null && $phpBinary !== '' ? $phpBinary : \PHP_BINARY);
    }

    /**
     * Get the path to a Composer-installed tool binary.
     *
     * @return non-empty-string
     */
    public function toolBin(ToolInstance $instance): string
    {
        return Str\format(
            '%s/tools/%s/vendor/bin/%s',
            $this->rootDir,
            $instance->installSlug,
            $instance->tool->getPackageName(),
        );
    }

    /**
     * Resolve the native mago binary for a specific versioned install slug.
     *
     * @param non-empty-string $installSlug e.g. "mago-1.10.0"
     *
     * @return non-empty-string
     */
    public function magoBinaryFor(string $installSlug): string
    {
        $toolDir = Str\format('%s/tools/%s', $this->rootDir, $installSlug);
        $version = Str\after($installSlug, 'mago-') ?? $installSlug;

        // TODO(azjezz): hardcoded platform, improve this later.
        return Str\format(
            '%s/vendor/carthage-software/mago/composer/bin/%s/mago-%s-aarch64-apple-darwin/mago',
            $toolDir,
            $version,
            $version,
        );
    }
}
