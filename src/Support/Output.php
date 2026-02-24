<?php

declare(strict_types=1);

namespace CarthageSoftware\ToolChainBenchmarks\Support;

use Closure;
use Psl\Async;
use Psl\DateTime;
use Psl\IO;
use Psl\Math;
use Psl\Str;

/**
 * Modern terminal output facade.
 */
final readonly class Output
{
    /**
     * Render a bold title with a thin horizontal rule underneath.
     */
    public static function title(string $text): void
    {
        IO\write_line('  %s%s%s', Style::BOLD, $text, Style::NC);
        /** @var int<0, max> $width */
        $width = Str\width($text);

        IO\write_line('  %s', Str\repeat(Style::RULE_THIN, $width));
    }

    /**
     * Render a dotted key-value configuration line.
     *
     * Example: "  Runs ··········· 10"
     */
    public static function configLine(string $label, string $value): void
    {
        /** @var int<0, max> $dotCount */
        $dotCount = Math\maxva(1, 18 - Str\width($label));

        IO\write_line('  %s %s%s%s %s', $label, Style::DIM, Str\repeat(Style::DOT, $dotCount), Style::NC, $value);
    }

    /**
     * Render a section heading with an optional right-aligned badge.
     */
    public static function section(string $text, ?string $badge = null): void
    {
        IO\write_line('');

        $line = Str\format('  %s%s%s', Style::BOLD, $text, Style::NC);
        if ($badge !== null) {
            $line .= Str\format('  %s%s%s', Style::DIM, $badge, Style::NC);
        }

        IO\write_line('%s', $line);
        IO\write_line('');
    }

    public static function success(string $message): void
    {
        IO\write_line('  %s%s%s %s', Style::GREEN, Style::CHECK, Style::NC, $message);
    }

    public static function warn(string $message): void
    {
        IO\write_line('  %s%s%s %s', Style::YELLOW, Style::WARNING, Style::NC, $message);
    }

    public static function error(string $message): void
    {
        IO\write_error_line('  %s%s%s %s', Style::RED, Style::CROSS, Style::NC, $message);
    }

    public static function info(string $message): void
    {
        IO\write_line('  %s%s%s %s', Style::DIM, Style::ARROW, Style::NC, $message);
    }

    public static function write(string $message): void
    {
        IO\write_line('%s', $message);
    }

    public static function blank(): void
    {
        IO\write_line('');
    }

    /**
     * Run a closure with an animated spinner, returning the closure's result.
     *
     * @template T
     *
     * @param (Closure(): T) $work
     *
     * @return T
     */
    public static function withSpinner(string $message, Closure $work, string $indent = '    '): mixed
    {
        $spinner = new Spinner($message, $indent);
        $awaitable = Async\run($work);

        while (!$awaitable->isComplete()) {
            Async\sleep(DateTime\Duration::milliseconds(80));
            $spinner->tick();
        }

        try {
            $result = $awaitable->await();
        } catch (\Throwable $e) {
            $spinner->fail($message);

            throw $e;
        }

        $spinner->succeed($message);

        return $result;
    }
}
