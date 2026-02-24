<?php

declare(strict_types=1);

namespace CarthageSoftware\ToolChainBenchmarks\Configuration;

use Psl\File;
use Psl\Filesystem;
use Psl\Str;

/**
 * Processes config template files by replacing placeholders.
 */
final readonly class Config
{
    /**
     * Process a config template, replacing {{WORKSPACE}} and {{CACHE_DIR}}.
     *
     * @param non-empty-string $templateFile
     * @param non-empty-string $outputFile
     * @param non-empty-string $workspace
     * @param non-empty-string $cacheDir
     */
    public static function processTemplate(
        string $templateFile,
        string $outputFile,
        string $workspace,
        string $cacheDir,
    ): void {
        $content = File\read($templateFile);
        $content = Str\replace($content, '{{WORKSPACE}}', $workspace);
        $content = Str\replace($content, '{{CACHE_DIR}}', $cacheDir);

        if (Filesystem\is_file($outputFile)) {
            Filesystem\delete_file($outputFile);
        }

        File\write($outputFile, $content, File\WriteMode::MustCreate);
    }
}
