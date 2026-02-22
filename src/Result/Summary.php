<?php

declare(strict_types=1);

namespace CarthageSoftware\StaticAnalyzersBenchmark\Result;

use CarthageSoftware\StaticAnalyzersBenchmark\Configuration\Project;
use Psl\DateTime;
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
        $content = Str\format("# Benchmark Results - %s\n\n", DateTime\Timestamp::now()->format('yyyy-MM-dd HH:mm:ss'));
        $content .= Str\format("Configuration: %d runs, %d warmup\n\n", $runs, $warmup);

        File\write($this->path, $content);
    }

    public function writeProjectHeading(Project $project): void
    {
        $this->append(Str\format("## %s\n\n", $project->getDisplayName()));
    }

    /**
     * @param non-empty-string $mdFile
     */
    public function writeBenchmarkType(string $label, string $mdFile): void
    {
        $content = Str\format("### %s\n\n", $label);

        if (Filesystem\exists($mdFile)) {
            $content .= File\read($mdFile);
            $content .= "\n";
        }

        $this->append($content);
    }

    /**
     * @param list<MemoryResult> $memoryResults
     */
    public function writeMemory(array $memoryResults, string $label): void
    {
        $content = Str\format("### %s\n\n", $label);
        $content .= "| Analyzer | Peak Memory (MB) |\n";
        $content .= "|----------|------------------|\n";

        foreach ($memoryResults as $memResult) {
            $content .= Str\format("| %s | %s |\n", $memResult->analyzerName, $memResult->formatMb());
        }

        $content .= "\n";
        $this->append($content);
    }

    private function append(string $content): void
    {
        File\write($this->path, $content, File\WriteMode::Append);
    }
}
