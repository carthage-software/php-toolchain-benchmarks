<?php

declare(strict_types=1);

namespace CarthageSoftware\StaticAnalyzersBenchmark\Configuration;

use Psl\File;
use Psl\Filesystem;
use Psl\Str;

/**
 * Resolved binary paths for all tools used in benchmark runs.
 */
final readonly class ToolPaths
{
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
     * Get the path to a Composer-installed analyzer binary.
     *
     * @return non-empty-string
     */
    public function analyzerBin(AnalyzerTool $tool): string
    {
        return Str\format('%s/tools/%s/vendor/bin/%s', $this->rootDir, $tool->slug, $tool->analyzer->value);
    }

    /**
     * Resolve the native mago binary for a specific versioned slug.
     *
     * @param non-empty-string $slug e.g. "mago-1.9.1"
     *
     * @return non-empty-string
     */
    public function magoBinaryFor(string $slug): string
    {
        $toolDir = Str\format('%s/tools/%s', $this->rootDir, $slug);
        $platformFile = Str\format('%s/vendor/carthage-software/mago/composer/bin/.platform', $toolDir);
        if (!Filesystem\is_file($platformFile)) {
            return Str\format('%s/vendor/bin/mago', $toolDir);
        }

        $relativePath = Str\trim(File\read($platformFile));

        return Str\format('%s/vendor/carthage-software/mago/composer/bin/%s', $toolDir, $relativePath);
    }
}
