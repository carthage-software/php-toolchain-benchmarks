<?php

declare(strict_types=1);

namespace CarthageSoftware\StaticAnalyzersBenchmark;

use CarthageSoftware\StaticAnalyzersBenchmark\Benchmark\Benchmark;
use CarthageSoftware\StaticAnalyzersBenchmark\Benchmark\BenchmarkFilter;
use CarthageSoftware\StaticAnalyzersBenchmark\Configuration\Analyzer;
use CarthageSoftware\StaticAnalyzersBenchmark\Configuration\BenchmarkCategory;
use CarthageSoftware\StaticAnalyzersBenchmark\Configuration\Project;
use CarthageSoftware\StaticAnalyzersBenchmark\Configuration\ToolPaths;
use CarthageSoftware\StaticAnalyzersBenchmark\Support\Output;
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
            'build' => Site\SiteBuilder::run($rootDir),
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
        /** @var null|non-empty-string $phpBinary */
        $phpBinary = null;

        $i = 0;
        while ($i < Iter\count($args)) {
            $arg = $args[$i];
            match ($arg) {
                '--runs' => $runs = Math\max([(int) ($args[++$i] ?? '10'), 1]),
                '--warmup' => $warmup = Math\max([(int) ($args[++$i] ?? '2'), 0]),
                '--project' => $filterProject = Project::tryFrom($args[++$i] ?? ''),
                '--analyzer' => $filterAnalyzer = Analyzer::tryFrom($args[++$i] ?? ''),
                '--category' => $filterCategory = BenchmarkCategory::tryFrom($args[++$i] ?? ''),
                '--php-binary' => $phpBinary = ($args[++$i] ?? '') !== '' ? $args[$i] : null,
                '--skip-stability' => $skipStability = true,
                default => null,
            };

            $i++;
        }

        $benchmark = new Benchmark(
            tools: ToolPaths::resolve($rootDir, $phpBinary),
            runs: Type\positive_int()->assert($runs),
            warmup: Type\uint()->assert($warmup),
            skipStability: $skipStability,
            filter: new BenchmarkFilter(analyzer: $filterAnalyzer, project: $filterProject, category: $filterCategory),
        );

        return $benchmark->execute();
    }

    private static function printUsage(): int
    {
        Output::write('PHP Static Analyzer Benchmarks');
        Output::blank();
        Output::write('Usage:');
        Output::write('  bin/benchmark setup                Setup: clone projects, install deps');
        Output::write('  bin/benchmark run [OPTIONS]        Run benchmarks');
        Output::write('  bin/benchmark build                Build HTML results page');
        Output::blank();
        Output::write('Options:');
        Output::write('  --runs N           Number of benchmark runs (default: 10)');
        Output::write('  --warmup N         Number of warmup runs (default: 2)');
        Output::write('  --project NAME     Only benchmark: psl, wordpress');
        Output::write('  --analyzer NAME    Only benchmark: mago, phpstan, psalm, phan');
        Output::write('  --category NAME    Only run: uncached, cached');
        Output::write('  --php-binary PATH  PHP binary to use (default: current PHP)');
        Output::write('  --skip-stability   Skip CPU stability check');

        return 0;
    }

    private static function unknownCommand(string $command): int
    {
        Output::error(Str\format('Unknown command: %s', $command));
        self::printUsage();
        return 1;
    }
}
