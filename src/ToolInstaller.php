<?php

declare(strict_types=1);

namespace CarthageSoftware\StaticAnalyzersBenchmark;

use CarthageSoftware\StaticAnalyzersBenchmark\Configuration\Analyzer;
use CarthageSoftware\StaticAnalyzersBenchmark\Configuration\AnalyzerTool;
use CarthageSoftware\StaticAnalyzersBenchmark\Support\Output;
use Psl\File;
use Psl\Filesystem;
use Psl\Json;
use Psl\Shell;
use Psl\Str;
use Psl\Vec;

/**
 * Installs each analyzer version into its own isolated tools/<slug>/ directory,
 * preventing autoloader conflicts between the benchmark suite and the tools.
 */
final readonly class ToolInstaller
{
    /**
     * All analyzer versions to install.
     *
     * @var list<array{non-empty-string, non-empty-string, non-empty-string}>
     */
    private const array TOOLS = [
        ['mago',    'carthage-software/mago', '1.9.1'],
        ['mago',    'carthage-software/mago', '1.8.0'],
        ['mago',    'carthage-software/mago', '1.7.0'],
        ['phpstan', 'phpstan/phpstan',        '2.1.39'],
        ['phpstan', 'phpstan/phpstan',        '2.1.34'],
        ['phpstan', 'phpstan/phpstan',        '2.1.30'],
        ['psalm',   'vimeo/psalm',            '7.0.0-beta16'],
        ['psalm',   'vimeo/psalm',            '6.15.1'],
        ['phan',    'phan/phan',              '6.0.1'],
    ];

    /**
     * Plugins that need to be allowed per analyzer type.
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
        Output::section('Installing isolated analyzer tools');

        $toolsDir = $rootDir . '/tools';
        Filesystem\create_directory($toolsDir);

        foreach (self::TOOLS as [$name, $package, $version]) {
            $slug = Str\format('%s-%s', $name, $version);
            if (!self::installTool($toolsDir, $name, $slug, $package, $version)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<AnalyzerTool>
     */
    public static function allTools(): array
    {
        return Vec\map(
            self::TOOLS,
            static fn(array $entry): AnalyzerTool => new AnalyzerTool(
                Analyzer::from($entry[0]),
                $entry[2],
                Str\format('%s-%s', $entry[0], $entry[2]),
            ),
        );
    }

    private static function installTool(
        string $toolsDir,
        string $name,
        string $slug,
        string $package,
        string $version,
    ): bool {
        $toolDir = Str\format('%s/%s', $toolsDir, $slug);
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

        $displayName = Analyzer::from($name)->name;
        $label = Str\format('%s %s', $displayName, $version);
        try {
            Output::withSpinner(
                Str\format('Installing %s', $label),
                static function () use ($toolDir, $name): void {
                    $args = ['install', '--no-interaction'];
                    if ($name === 'phan') {
                        $args[] = '--ignore-platform-req=ext-tokenizer';
                    }

                    Shell\execute('composer', $args, $toolDir);

                    if ($name === 'mago') {
                        Shell\execute('composer', ['mago:install-binary', '--no-interaction'], $toolDir);
                    }
                },
                '  ',
            );

            return true;
        } catch (Shell\Exception\FailedExecutionException $e) {
            Output::error(Str\format('Failed to install %s: %s', $label, $e->getErrorOutput()));
            return false;
        }
    }
}
