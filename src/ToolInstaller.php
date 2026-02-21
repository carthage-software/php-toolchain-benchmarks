<?php

declare(strict_types=1);

namespace CarthageSoftware\StaticAnalyzersBenchmark;

use CarthageSoftware\StaticAnalyzersBenchmark\Configuration\Analyzer;
use CarthageSoftware\StaticAnalyzersBenchmark\Support\Console;
use Psl\File;
use Psl\Filesystem;
use Psl\Json;
use Psl\Shell;
use Psl\Str;

/**
 * Installs each analyzer into its own isolated tools/<name>/ directory,
 * preventing autoloader conflicts between the benchmark suite and the tools.
 */
final readonly class ToolInstaller
{
    /**
     * Composer package + exact version for each isolated tool.
     *
     * @var array<string, array{non-empty-string, non-empty-string}>
     */
    private const array TOOLS = [
        'mago' => ['carthage-software/mago', '1.9.1'],
        'phpstan' => ['phpstan/phpstan', '2.1.39'],
        'psalm' => ['vimeo/psalm', '7.0.0-beta16'],
        'phan' => ['phan/phan', '6.0.1'],
    ];

    /**
     * Plugins that need to be allowed per tool.
     *
     * @var array<string, list<non-empty-string>>
     */
    private const array ALLOWED_PLUGINS = [
        'mago' => ['carthage-software/mago'],
        'phpstan' => ['phpstan/extension-installer'],
    ];

    /**
     * @param non-empty-string $rootDir
     */
    public static function install(string $rootDir): bool
    {
        Console::heading('Installing isolated analyzer tools');

        $toolsDir = $rootDir . '/tools';
        Filesystem\create_directory($toolsDir);

        foreach (self::TOOLS as $name => [$package, $version]) {
            if (!self::installTool($toolsDir, $name, $package, $version)) {
                return false;
            }
        }

        return true;
    }

    private static function installTool(string $toolsDir, string $name, string $package, string $version): bool
    {
        $analyzer = Analyzer::from($name);
        $toolDir = Str\format('%s/%s', $toolsDir, $name);
        Filesystem\create_directory($toolDir);

        $allowPlugins = [];
        foreach (self::ALLOWED_PLUGINS[$name] ?? [] as $plugin) {
            $allowPlugins[$plugin] = true;
        }

        $composerJson = Str\format('%s/composer.json', $toolDir);
        $composerContent = Json\encode([
            'require' => [
                $package => $version,
            ],
            'config' => [
                'allow-plugins' => $allowPlugins !== [] ? $allowPlugins : (object) [],
                'platform-check' => false,
            ],
        ], true);

        if (Filesystem\is_file($composerJson)) {
            Filesystem\delete_file($composerJson);
        }

        File\write($composerJson, $composerContent, File\WriteMode::MustCreate);

        Console::info(Str\format('Installing %s (%s)...', $analyzer->getDisplayName(), $version));
        try {
            Shell\execute('composer', ['install', '--no-interaction'], $toolDir);

            if ($name === 'mago') {
                Shell\execute('composer', ['mago:install-binary', '--no-interaction'], $toolDir);
            }

            Console::success(Str\format('%s installed', $analyzer->getDisplayName()));
            return true;
        } catch (Shell\Exception\FailedExecutionException $e) {
            Console::error(Str\format('Failed to install %s: %s', $analyzer->getDisplayName(), $e->getErrorOutput()));
            return false;
        }
    }
}
