<?php

declare(strict_types=1);

namespace CarthageSoftware\StaticAnalyzersBenchmark\Configuration;

use Psl\File;
use Psl\Filesystem;
use Psl\Str;

final readonly class Config
{
    /**
     * Process a config template, replacing placeholders with actual paths.
     *
     * @param non-empty-string $templateFile Path to the template file.
     * @param non-empty-string $outputFile   Path to write the processed config.
     * @param non-empty-string $workspace    Absolute path to the cloned project.
     * @param non-empty-string $cacheDir     Absolute path to the cache directory.
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

        Filesystem\create_directory_for_file($outputFile);
        if (Filesystem\is_file($outputFile)) {
            Filesystem\delete_file($outputFile);
        }

        File\write($outputFile, $content, File\WriteMode::MustCreate);
    }
}
