<?php

declare(strict_types=1);

namespace CarthageSoftware\StaticAnalyzersBenchmark\Support;

use Psl\IO;

final readonly class Console
{
    private const string BLUE = "\033[0;34m";
    private const string GREEN = "\033[0;32m";
    private const string YELLOW = "\033[0;33m";
    private const string RED = "\033[0;31m";
    private const string BOLD = "\033[1m";
    private const string NC = "\033[0m";

    public static function info(string $message): void
    {
        IO\write_line(self::BLUE . '[INFO]' . self::NC . ' %s', $message);
    }

    public static function success(string $message): void
    {
        IO\write_line(self::GREEN . '[OK]' . self::NC . ' %s', $message);
    }

    public static function warn(string $message): void
    {
        IO\write_line(self::YELLOW . '[WARN]' . self::NC . ' %s', $message);
    }

    public static function error(string $message): void
    {
        IO\write_error_line(self::RED . '[ERROR]' . self::NC . ' %s', $message);
    }

    public static function heading(string $title): void
    {
        IO\write_line('');
        IO\write_line(self::BOLD . '=== %s ===' . self::NC, $title);
        IO\write_line('');
    }

    public static function write(string $message): void
    {
        IO\write_line('%s', $message);
    }

    public static function blank(): void
    {
        IO\write_line('');
    }
}
