<?php

declare(strict_types=1);

namespace CarthageSoftware\StaticAnalyzersBenchmark\Configuration;

use Psl\Str;

enum IncrementalVariant: string
{
    case NoChange = 'none';
    case Touch = 'touch';
    case Noop = 'noop';
    case Logic = 'logic';

    public function getLabel(): string
    {
        return match ($this) {
            self::NoChange => 'No change (cache hit)',
            self::Touch => 'Touch (mtime only)',
            self::Noop => 'No-op change',
            self::Logic => 'Logic change',
        };
    }

    /**
     * Returns the shell command that modifies the target file for this variant.
     *
     * @param non-empty-string $file
     *
     * @return non-empty-string
     */
    public function getModifyCommand(string $file): string
    {
        return match ($this) {
            self::NoChange => 'true',
            self::Touch => Str\format('touch %s', $file),
            self::Noop => Str\format("echo '; ' >> %s", $file),
            self::Logic => Str\format("echo 'echo [];' >> %s", $file),
        };
    }
}
