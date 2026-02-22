<?php

declare(strict_types=1);

namespace CarthageSoftware\StaticAnalyzersBenchmark\Configuration;

enum Project: string
{
    case Psl = 'psl';
    case WordPress = 'wordpress';

    public function getDisplayName(): string
    {
        return match ($this) {
            self::Psl => 'azjezz/psl',
            self::WordPress => 'wordpress-develop',
        };
    }

    /**
     * @return non-empty-string
     */
    public function getRepo(): string
    {
        return match ($this) {
            self::Psl => 'https://github.com/azjezz/psl.git',
            self::WordPress => 'https://github.com/WordPress/wordpress-develop.git',
        };
    }

    /**
     * @return non-empty-string
     */
    public function getRef(): string
    {
        return match ($this) {
            self::Psl => 'next',
            self::WordPress => 'trunk',
        };
    }

    /**
     * Returns the composer command to set up this project after cloning.
     *
     * @return non-empty-string
     */
    public function getSetupCommand(): string
    {
        return 'composer update --no-interaction --quiet --ignore-platform-reqs';
    }
}
