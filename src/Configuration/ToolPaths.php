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
     * @param non-empty-string $magoBinary
     */
    public function __construct(
        public string $rootDir,
        public string $phpBinary,
        public string $magoBinary,
    ) {}

    /**
     * @param non-empty-string $rootDir
     */
    public static function resolve(string $rootDir, ?string $phpBinary = null, ?string $magoBinary = null): self
    {
        return new self(
            $rootDir,
            $phpBinary !== null && $phpBinary !== '' ? $phpBinary : \PHP_BINARY,
            $magoBinary !== null && $magoBinary !== '' ? $magoBinary : self::defaultMagoBinary($rootDir),
        );
    }

    /**
     * Get the path to a Composer-installed analyzer binary.
     *
     * @return non-empty-string
     */
    public function analyzerBin(Analyzer $analyzer): string
    {
        return Str\format('%s/tools/%s/vendor/bin/%s', $this->rootDir, $analyzer->value, $analyzer->value);
    }

    /**
     * Resolve the native mago binary path via the .platform file.
     *
     * Falls back to the Composer proxy if the .platform file is missing.
     *
     * @param non-empty-string $rootDir
     *
     * @return non-empty-string
     */
    private static function defaultMagoBinary(string $rootDir): string
    {
        $toolDir = Str\format('%s/tools/mago', $rootDir);
        $platformFile = Str\format('%s/vendor/carthage-software/mago/composer/bin/.platform', $toolDir);
        if (!Filesystem\is_file($platformFile)) {
            return Str\format('%s/vendor/bin/mago', $toolDir);
        }

        $relativePath = Str\trim(File\read($platformFile));

        return Str\format('%s/vendor/carthage-software/mago/composer/bin/%s', $toolDir, $relativePath);
    }
}
