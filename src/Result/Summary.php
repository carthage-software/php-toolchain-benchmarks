<?php

declare(strict_types=1);

namespace CarthageSoftware\StaticAnalyzersBenchmark\Result;

use CarthageSoftware\StaticAnalyzersBenchmark\Configuration\IncrementalVariant;
use CarthageSoftware\StaticAnalyzersBenchmark\Configuration\Project;
use Psl\File;
use Psl\Filesystem;
use Psl\Str;

final class Summary
{
    /** @var non-empty-string */
    private string $path;

    /**
     * @param non-empty-string $resultsDir
     */
    public function __construct(string $resultsDir)
    {
        $this->path = $resultsDir . '/summary.md';
    }

    /**
     * @return non-empty-string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    public function writeHeader(int $runs, int $warmup): void
    {
        $content = Str\format("# Benchmark Results - %s\n\n", \date('Y-m-d H:i:s'));
        $content .= Str\format("Configuration: %d runs, %d warmup\n\n", $runs, $warmup);

        File\write($this->path, $content);
    }

    public function writeProjectHeading(Project $project): void
    {
        $this->append(Str\format("## %s\n\n", $project->getDisplayName()));
    }

    /**
     * @param non-empty-string       $categoryName
     * @param list<MemoryResult>     $memoryResults
     * @param non-empty-string       $mdFile
     */
    public function writeCategory(string $categoryName, array $memoryResults, string $mdFile): void
    {
        $content = Str\format("### %s\n\n", $categoryName);

        // Memory table
        $content .= "| Analyzer | Peak Memory (MB) |\n";
        $content .= "|----------|------------------|\n";

        foreach ($memoryResults as $memResult) {
            $content .= Str\format("| %s | %s |\n", $memResult->analyzerName, $memResult->formatMb());
        }

        $content .= "\n";

        // Append hyperfine markdown table if available
        if (Filesystem\exists($mdFile)) {
            $content .= File\read($mdFile);
            $content .= "\n";
        }

        $this->append($content);
    }

    /**
     * @param non-empty-string $incFile
     */
    public function writeIncrementalHeader(string $incFile): void
    {
        $basename = Filesystem\get_basename($incFile);
        $this->append(Str\format("### Incremental (Cache Invalidation)\n\nTarget file: `%s`\n\n", $basename));
    }

    /**
     * @param non-empty-string $mdFile
     */
    public function writeIncrementalVariant(IncrementalVariant $variant, string $mdFile): void
    {
        $content = Str\format("#### %s\n\n", $variant->getLabel());

        if (Filesystem\exists($mdFile)) {
            $content .= File\read($mdFile);
            $content .= "\n";
        }

        $this->append($content);
    }

    private function append(string $content): void
    {
        File\write($this->path, $content, File\WriteMode::Append);
    }
}
