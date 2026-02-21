<?php

declare(strict_types=1);

namespace CarthageSoftware\StaticAnalyzersBenchmark\Result;

use Psl\File;
use Psl\Json;
use Psl\Str;
use Psl\Type;

/**
 * Parsed result from a hyperfine JSON export.
 *
 * @psalm-immutable
 */
final readonly class HyperfineResult
{
    /**
     * @param list<array{
     *     command: string,
     *     mean: float,
     *     stddev: null|float,
     *     min: float,
     *     max: float,
     *     times: list<float>,
     *     exit_codes: list<int>,
     *     ...
     * }> $results
     */
    public function __construct(
        public array $results,
    ) {}

    /**
     * Parse a hyperfine JSON output file.
     *
     * @param non-empty-string $jsonFile
     */
    public static function fromFile(string $jsonFile): self
    {
        $json = File\read($jsonFile);

        $data = Json\typed($json, Type\shape([
            'results' => Type\vec(Type\shape([
                'command' => Type\string(),
                'mean' => Type\float(),
                'stddev' => Type\nullable(Type\float()),
                'min' => Type\float(),
                'max' => Type\float(),
                'times' => Type\vec(Type\float()),
                'exit_codes' => Type\vec(Type\int()),
            ], true)),
        ], true));

        return new self($data['results']);
    }

    /**
     * Format results as a summary string for logging.
     */
    public function formatSummary(): string
    {
        $lines = [];
        foreach ($this->results as $result) {
            $stddev = $result['stddev'] ?? 0.0;
            $lines[] = Str\format(
                '  %s: %.3fs ± %.3fs (min: %.3fs, max: %.3fs)',
                $result['command'],
                $result['mean'],
                $stddev,
                $result['min'],
                $result['max'],
            );
        }

        return Str\join($lines, "\n");
    }
}
