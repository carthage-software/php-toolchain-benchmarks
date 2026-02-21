<?php

declare(strict_types=1);

namespace CarthageSoftware\StaticAnalyzersBenchmark\Support;

use CarthageSoftware\StaticAnalyzersBenchmark\Result\HyperfineResult;
use Psl\Filesystem;
use Psl\Shell;
use Psl\Str;

/**
 * Shell execution utilities for benchmark runners.
 *
 * All commands run with /tmp as working directory to prevent PHP-based analyzers
 * from discovering the benchmark suite's vendor/autoload.php and conflicting
 * with the workspace project's autoloader.
 */
final readonly class ShellHelper
{
    /**
     * Run hyperfine with the given arguments and display output.
     *
     * @param list<string> $args
     */
    public static function runHyperfine(array $args): void
    {
        try {
            Shell\execute('hyperfine', $args, '/tmp', error_output_behavior: Shell\ErrorOutputBehavior::Append);
        } catch (Shell\Exception\FailedExecutionException $e) {
            Console::warn(Str\format('Hyperfine exited with code %d', $e->getCode()));
        }
    }

    /**
     * Parse hyperfine JSON results.
     *
     * @param non-empty-string $jsonFile
     */
    public static function parseResults(string $jsonFile): ?HyperfineResult
    {
        if (!Filesystem\exists($jsonFile)) {
            return null;
        }

        return HyperfineResult::fromFile($jsonFile);
    }

    /**
     * Execute a shell command string, returning false on failure.
     */
    public static function exec(string $command): bool
    {
        try {
            Shell\execute('sh', ['-c', $command], '/tmp');
            return true;
        } catch (Shell\Exception\FailedExecutionException) {
            return false;
        }
    }
}
