<?php

declare(strict_types=1);

namespace CarthageSoftware\StaticAnalyzersBenchmark\Configuration;

enum BenchmarkCategory: string
{
    case Uncached = 'uncached';
    case Incremental = 'incremental';
}
