<?php

declare(strict_types=1);

namespace CarthageSoftware\ToolChainBenchmarks;

use CarthageSoftware\ToolChainBenchmarks\Benchmark\Benchmark;
use CarthageSoftware\ToolChainBenchmarks\Benchmark\BenchmarkFilter;
use CarthageSoftware\ToolChainBenchmarks\Configuration\Project;
use CarthageSoftware\ToolChainBenchmarks\Configuration\Tool;
use CarthageSoftware\ToolChainBenchmarks\Configuration\ToolKind;
use CarthageSoftware\ToolChainBenchmarks\Configuration\ToolPaths;
use CarthageSoftware\ToolChainBenchmarks\Support\Output;
use Psl\DateTime\Duration;
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
            'setup' => Setup\Setup::run($rootDir),
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
        /** @var null|int<1, max> $timeout */
        $timeout = null;
        $skipStability = false;
        $filterProject = null;
        $filterKind = null;
        $filterTool = null;
        /** @var null|non-empty-string $phpBinary */
        $phpBinary = null;

        $i = 0;
        while ($i < Iter\count($args)) {
            $arg = $args[$i];
            match ($arg) {
                '--runs' => $runs = Math\max([(int) ($args[++$i] ?? '10'), 1]),
                '--timeout' => $timeout = Math\max([(int) ($args[++$i] ?? '5'), 1]),
                '--project' => $filterProject = Project::tryFrom($args[++$i] ?? ''),
                '--kind' => $filterKind = ToolKind::tryFrom($args[++$i] ?? ''),
                '--tool' => $filterTool = Tool::tryFrom($args[++$i] ?? ''),
                '--php-binary' => $phpBinary = ($args[++$i] ?? '') !== '' ? $args[$i] : null,
                '--skip-stability' => $skipStability = true,
                default => null,
            };

            $i++;
        }

        $benchmark = new Benchmark(
            tools: ToolPaths::resolve($rootDir, $phpBinary),
            runs: Type\positive_int()->assert($runs),
            timeout: $timeout !== null ? Duration::minutes($timeout) : null,
            skipStability: $skipStability,
            filter: new BenchmarkFilter(kind: $filterKind, tool: $filterTool, project: $filterProject),
        );

        return $benchmark->execute();
    }

    private static function printUsage(): int
    {
        Output::write('PHP Toolchain Benchmarks');
        Output::blank();
        Output::write('Usage:');
        Output::write('  ./src/main.php setup            Setup: clone projects, install deps');
        Output::write('  ./src/main.php run [OPTIONS]    Run benchmarks');
        Output::write('  ./src/main.php build            Build HTML results page');
        Output::blank();
        Output::write('Options:');
        Output::write('  --runs N           Number of benchmark runs (default: 10)');
        Output::write('  --project NAME     Only benchmark: psl, wordpress, magento');
        Output::write('  --kind NAME        Only benchmark: formatter, linter, analyzer');
        Output::write('  --tool NAME        Only benchmark: mago-fmt, pretty-php, mago-lint, ...');
        Output::write('  --timeout N        Timeout per run in minutes (default: 5)');
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
