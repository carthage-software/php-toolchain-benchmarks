<?php

declare(strict_types=1);

namespace CarthageSoftware\ToolChainBenchmarks\Configuration;

enum ToolKind: string
{
    case Formatter = 'formatter';
    case Linter = 'linter';
    case Analyzer = 'analyzer';
    case Guard = 'guard';

    /**
     * @return non-empty-string
     */
    public function getDisplayName(): string
    {
        return match ($this) {
            self::Formatter => 'Formatters',
            self::Linter => 'Linters',
            self::Analyzer => 'Analyzers',
            self::Guard => 'Architecture Guards',
        };
    }

    /**
     * Returns the benchmark categories for this tool kind.
     *
     * Analyzers and architecture guards are measured cold (caches cleared) and hot (caches warmed).
     *
     * @return list<non-empty-string>
     */
    public function getCategories(): array
    {
        return match ($this) {
            self::Formatter => ['Formatter'],
            self::Linter => ['Linter'],
            self::Analyzer, self::Guard => ['Cold', 'Hot'],
        };
    }
}
