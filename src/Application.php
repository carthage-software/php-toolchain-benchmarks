<?php

declare(strict_types=1);

namespace CarthageSoftware\StaticAnalyzersBenchmark;

use CarthageSoftware\StaticAnalyzersBenchmark\Benchmark\Benchmark;
use CarthageSoftware\StaticAnalyzersBenchmark\Benchmark\BenchmarkFilter;
use CarthageSoftware\StaticAnalyzersBenchmark\Configuration\Analyzer;
use CarthageSoftware\StaticAnalyzersBenchmark\Configuration\BenchmarkCategory;
use CarthageSoftware\StaticAnalyzersBenchmark\Configuration\Project;
use CarthageSoftware\StaticAnalyzersBenchmark\Support\Console;
use Psl\Iter;
use Psl\Math;
use Psl\Str;
use Psl\Type;
use Psl\Vec;

final class Application
{
    /**
     * @param list<string> $argv
     */
    public static function run(array $argv): int
    {
        $rootDir = \dirname(__DIR__);
        $args = Vec\slice($argv, 1);

        if ($args === []) {
            self::printUsage();
            return 1;
        }

        $command = $args[0];
        $rest = Vec\slice($args, 1);

        return match ($command) {
            'setup' => Setup::run($rootDir),
            'run' => self::runBenchmark($rootDir, $rest),
            'help', '--help', '-h' => self::printUsage(),
            default => self::unknownCommand($command),
        };
    }

    /**
     * @param non-empty-string $rootDir
     * @param list<string>     $args
     */
    private static function runBenchmark(string $rootDir, array $args): int
    {
        /** @var int<1, max> $runs */
        $runs = 10;
        /** @var int<0, max> $warmup */
        $warmup = 2;
        $skipStability = false;
        $filterProject = null;
        $filterAnalyzer = null;
        $filterCategory = null;

        $i = 0;
        while ($i < Iter\count($args)) {
            $arg = $args[$i];
            match ($arg) {
                '--runs' => $runs = Math\max([(int) ($args[++$i] ?? '10'), 1]),
                '--warmup' => $warmup = Math\max([(int) ($args[++$i] ?? '2'), 0]),
                '--project' => $filterProject = Project::tryFrom($args[++$i] ?? ''),
                '--analyzer' => $filterAnalyzer = Analyzer::tryFrom($args[++$i] ?? ''),
                '--category' => $filterCategory = BenchmarkCategory::tryFrom($args[++$i] ?? ''),
                '--skip-stability' => $skipStability = true,
                default => null,
            };

            $i++;
        }

        $benchmark = new Benchmark(
            rootDir: $rootDir,
            runs: Type\positive_int()->assert($runs),
            warmup: Type\uint()->assert($warmup),
            skipStability: $skipStability,
            filter: new BenchmarkFilter(analyzer: $filterAnalyzer, project: $filterProject, category: $filterCategory),
        );

        return $benchmark->execute();
    }

    private static function printUsage(): int
    {
        Console::write('PHP Static Analyzer Benchmarks');
        Console::blank();
        Console::write('Usage:');
        Console::write('  bin/benchmark setup                Setup: clone projects, install deps');
        Console::write('  bin/benchmark run [OPTIONS]        Run benchmarks');
        Console::blank();
        Console::write('Options:');
        Console::write('  --runs N           Number of benchmark runs (default: 10)');
        Console::write('  --warmup N         Number of warmup runs (default: 2)');
        Console::write('  --project NAME     Only benchmark: psl, wordpress');
        Console::write('  --analyzer NAME    Only benchmark: mago, phpstan, psalm, phan');
        Console::write('  --category NAME    Only run: uncached, incremental');
        Console::write('  --skip-stability   Skip CPU stability check');

        return 0;
    }

    private static function unknownCommand(string $command): int
    {
        Console::error(Str\format('Unknown command: %s', $command));
        self::printUsage();
        return 1;
    }
}
