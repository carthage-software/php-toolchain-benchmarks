<?php

declare(strict_types=1);

namespace CarthageSoftware\ToolChainBenchmarks\Configuration;

use Psl\Str;

/**
 * Each case represents a benchmarkable tool.
 *
 * Mago appears three times (fmt/lint/analyze) because each is a different benchmark target,
 * even though they share the same binary and package.
 */
enum Tool: string
{
    // Formatters
    case MagoFmt = 'mago-fmt';
    case PrettyPhp = 'pretty-php';

    // Linters
    case MagoLint = 'mago-lint';
    case PhpCsFixer = 'php-cs-fixer';
    case Phpcs = 'phpcs';

    // Analyzers
    case MagoAnalyze = 'mago-analyze';
    case PhpStan = 'phpstan';
    case Psalm = 'psalm';
    case Phan = 'phan';

    public function getKind(): ToolKind
    {
        return match ($this) {
            self::MagoFmt, self::PrettyPhp => ToolKind::Formatter,
            self::MagoLint, self::PhpCsFixer, self::Phpcs => ToolKind::Linter,
            self::MagoAnalyze, self::PhpStan, self::Psalm, self::Phan => ToolKind::Analyzer,
        };
    }

    /**
     * Short name used in the PACKAGES constant and install directory.
     *
     * @return non-empty-string
     */
    public function getPackageName(): string
    {
        return match ($this) {
            self::MagoFmt, self::MagoLint, self::MagoAnalyze => 'mago',
            self::PrettyPhp => 'pretty-php',
            self::PhpCsFixer => 'php-cs-fixer',
            self::Phpcs => 'phpcs',
            self::PhpStan => 'phpstan',
            self::Psalm => 'psalm',
            self::Phan => 'phan',
        };
    }

    /**
     * Full composer package name for installation.
     *
     * @return non-empty-string
     */
    public function getComposerPackage(): string
    {
        return match ($this) {
            self::MagoFmt, self::MagoLint, self::MagoAnalyze => 'carthage-software/mago',
            self::PrettyPhp => 'lkrms/pretty-php',
            self::PhpCsFixer => 'php-cs-fixer/shim',
            self::Phpcs => 'squizlabs/php_codesniffer',
            self::PhpStan => 'phpstan/phpstan',
            self::Psalm => 'vimeo/psalm',
            self::Phan => 'phan/phan',
        };
    }

    /**
     * Human-readable name prefix (without version).
     *
     * @return non-empty-string
     */
    public function getDisplayPrefix(): string
    {
        return match ($this) {
            self::MagoFmt => 'Mago Fmt',
            self::PrettyPhp => 'Pretty PHP',
            self::MagoLint => 'Mago Lint',
            self::PhpCsFixer => 'PHP-CS-Fixer',
            self::Phpcs => 'PHPCS',
            self::MagoAnalyze => 'Mago',
            self::PhpStan => 'PHPStan',
            self::Psalm => 'Psalm',
            self::Phan => 'Phan',
        };
    }

    /**
     * Whether this tool is a native binary (Mago) — no PHP or opcache needed.
     */
    public function isNative(): bool
    {
        return $this->getPackageName() === 'mago';
    }

    /**
     * Whether this tool supports caching (only some analyzers).
     */
    public function supportsCaching(): bool
    {
        return $this === self::PhpStan || $this === self::Psalm || $this === self::Phan;
    }

    /**
     * Config filename for this tool, version-aware for Psalm.
     *
     * Returns null for tools that don't use config files (Pretty PHP).
     *
     * @param non-empty-string $version
     *
     * @return non-empty-string|null
     */
    public function getConfigFilename(string $version): ?string
    {
        return match ($this) {
            self::MagoFmt, self::MagoLint, self::MagoAnalyze => 'mago.toml',
            self::PrettyPhp => null,
            self::PhpCsFixer => 'php-cs-fixer.php',
            self::Phpcs => 'phpcs.xml',
            self::PhpStan => 'phpstan.neon',
            self::Psalm => Str\format('psalm-v%s.xml', Str\before($version, '.') ?? $version),
            self::Phan => 'phan.php',
        };
    }
}
