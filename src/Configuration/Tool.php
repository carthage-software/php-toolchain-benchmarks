<?php

declare(strict_types=1);

namespace CarthageSoftware\ToolChainBenchmarks\Configuration;

use Psl\Str;

/**
 * Each case represents a benchmarkable tool.
 *
 * Mago appears four times (fmt/lint/analyze/guard) because each is a different benchmark target,
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

    // Architecture guards
    case MagoGuard = 'mago-guard';
    case Deptrac = 'deptrac';
    case StructArmed = 'structarmed';

    public function getKind(): ToolKind
    {
        return match ($this) {
            self::MagoFmt, self::PrettyPhp => ToolKind::Formatter,
            self::MagoLint, self::PhpCsFixer, self::Phpcs => ToolKind::Linter,
            self::MagoAnalyze, self::PhpStan, self::Psalm, self::Phan => ToolKind::Analyzer,
            self::MagoGuard, self::Deptrac, self::StructArmed => ToolKind::Guard,
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
            self::MagoFmt, self::MagoLint, self::MagoAnalyze, self::MagoGuard => 'mago',
            self::PrettyPhp => 'pretty-php',
            self::PhpCsFixer => 'php-cs-fixer',
            self::Phpcs => 'phpcs',
            self::PhpStan => 'phpstan',
            self::Psalm => 'psalm',
            self::Phan => 'phan',
            self::Deptrac => 'deptrac',
            self::StructArmed => 'structarmed',
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
            self::MagoFmt, self::MagoLint, self::MagoAnalyze, self::MagoGuard => 'carthage-software/mago',
            self::PrettyPhp => 'lkrms/pretty-php',
            self::PhpCsFixer => 'php-cs-fixer/shim',
            self::Phpcs => 'squizlabs/php_codesniffer',
            self::PhpStan => 'phpstan/phpstan',
            self::Psalm => 'vimeo/psalm',
            self::Phan => 'phan/phan',
            self::Deptrac => 'deptrac/deptrac',
            self::StructArmed => 'boundwize/structarmed',
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
            self::MagoGuard => 'Mago Guard',
            self::Deptrac => 'Deptrac',
            self::StructArmed => 'StructArmed',
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
     * Whether this tool supports caching (some analyzers and architecture guards).
     */
    public function supportsCaching(): bool
    {
        return match ($this) {
            self::PhpStan, self::Psalm, self::Phan, self::Deptrac, self::StructArmed => true,
            default => false,
        };
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
            self::MagoFmt, self::MagoLint, self::MagoAnalyze, self::MagoGuard => 'mago.toml',
            self::PrettyPhp => null,
            self::PhpCsFixer => 'php-cs-fixer.php',
            self::Phpcs => 'phpcs.xml',
            self::PhpStan => 'phpstan.neon',
            self::Psalm => Str\format('psalm-v%s.xml', Str\before($version, '.') ?? $version),
            self::Phan => 'phan.php',
            self::Deptrac => 'deptrac.yaml',
            self::StructArmed => 'structarmed.php',
        };
    }
}
