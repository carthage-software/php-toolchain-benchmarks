<?php

declare(strict_types=1);

namespace CarthageSoftware\StaticAnalyzersBenchmark\Benchmark;

use CarthageSoftware\StaticAnalyzersBenchmark\Configuration\ToolPaths;
use CarthageSoftware\StaticAnalyzersBenchmark\Support\Output;
use Psl\Shell;
use Psl\Str;

/**
 * Warns about PHP environment settings that can significantly affect benchmark results.
 */
final readonly class EnvironmentCheck
{
    public static function warn(ToolPaths $tools): void
    {
        $info = self::queryPhp($tools->phpBinary);
        if ($info === null) {
            return;
        }

        if (!$info['opcache']) {
            Output::warn('OPcache is disabled — PHP analyzers will be slower');
        }

        if ($info['assertions']) {
            Output::warn('PHP assertions are enabled (-dzend.assertions=1) — adds overhead');
        }

        if ($info['xdebug']) {
            Output::warn('Xdebug is loaded — significant performance impact');
        }
    }

    /**
     * @param non-empty-string $phpBinary
     *
     * @return null|array{opcache: bool, assertions: bool, xdebug: bool}
     */
    private static function queryPhp(string $phpBinary): ?array
    {
        try {
            $output = Shell\execute($phpBinary, [
                '-r',
                'echo json_encode(["opcache" => function_exists("opcache_get_status") && ini_get("opcache.enable_cli"), "assertions" => (int) ini_get("zend.assertions") === 1, "xdebug" => extension_loaded("xdebug")]);',
            ]);
        } catch (Shell\Exception\ExceptionInterface) {
            return null;
        }

        /** @var null|array{opcache: bool, assertions: bool, xdebug: bool} */
        return \json_decode(Str\trim($output), true);
    }
}
