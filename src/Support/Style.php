<?php

declare(strict_types=1);

namespace CarthageSoftware\StaticAnalyzersBenchmark\Support;

final readonly class Style
{
    // ANSI color codes
    public const string BLUE = "\033[0;34m";
    public const string CYAN = "\033[0;36m";
    public const string GREEN = "\033[0;32m";
    public const string YELLOW = "\033[0;33m";
    public const string RED = "\033[0;31m";
    public const string DIM = "\033[2m";
    public const string BOLD = "\033[1m";
    public const string NC = "\033[0m";

    // Terminal control
    public const string CLEAR_LINE = "\033[2K";

    // Unicode symbols
    public const string CHECK = "\u{2713}";
    public const string CROSS = "\u{2717}";
    public const string WARNING = "\u{26A0}";
    public const string ARROW = "\u{25B8}";
    public const string DOT = "\u{00B7}";
    public const string RULE_THIN = "\u{2500}";
    public const string RULE_THICK = "\u{2501}";
}
