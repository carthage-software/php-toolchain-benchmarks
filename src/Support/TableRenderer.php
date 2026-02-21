<?php

declare(strict_types=1);

namespace CarthageSoftware\StaticAnalyzersBenchmark\Support;

use Psl\Math;
use Psl\Str;
use Psl\Vec;

/**
 * Renders box-drawing tables for console output.
 */
final readonly class TableRenderer
{
    /**
     * Render a box-drawing table with headers and rows.
     *
     * @param list<string> $headers
     * @param list<list<string>> $rows
     */
    public static function render(array $headers, array $rows, string $indent = ''): string
    {
        $colWidths = self::computeColumnWidths($headers, $rows);

        $lines = [
            self::border($colWidths, '┌', '┬', '┐', $indent),
            self::formatRow($headers, $colWidths, $indent),
            self::border($colWidths, '├', '┼', '┤', $indent),
        ];

        foreach ($rows as $row) {
            $lines[] = self::formatRow($row, $colWidths, $indent);
        }

        $lines[] = self::border($colWidths, '└', '┴', '┘', $indent);

        return Str\join($lines, "\n");
    }

    /**
     * Get the display width of a string, accounting for wide characters (emoji).
     *
     * @return int<0, max>
     */
    private static function displayWidth(string $s): int
    {
        /** @var int<0, max> */
        return Str\width($s);
    }

    /**
     * @param list<string> $headers
     * @param list<list<string>> $rows
     *
     * @return list<int<0, max>>
     */
    private static function computeColumnWidths(array $headers, array $rows): array
    {
        return Vec\map_with_key($headers, static function (int $i, string $h) use ($rows): int {
            $max = self::displayWidth($h);
            foreach ($rows as $row) {
                $max = Math\maxva($max, self::displayWidth($row[$i] ?? ''));
            }

            return $max;
        });
    }

    /**
     * @param list<string> $cells
     * @param list<int<0, max>> $colWidths
     */
    private static function formatRow(array $cells, array $colWidths, string $indent): string
    {
        /** @var int<0, max> $i */
        $parts = Vec\map_with_key($colWidths, static function (int $i, int $w) use ($cells): string {
            $cell = $cells[$i] ?? '';
            /** @var int<0, max> $pad */
            $pad = Math\maxva(0, $w - self::displayWidth($cell));

            return ' ' . $cell . Str\repeat(' ', $pad) . ' ';
        });

        return $indent . '│' . Str\join($parts, '│') . '│';
    }

    /**
     * @param list<int<0, max>> $colWidths
     */
    private static function border(array $colWidths, string $left, string $mid, string $right, string $indent): string
    {
        return (
            $indent
            . $left
            . Str\join(
                Vec\map(
                    $colWidths,
                    /**
                     * @param int<0, max> $w
                     */
                    static fn(int $w): string => Str\repeat('─', $w + 2),
                ),
                $mid,
            )
            . $right
        );
    }
}
