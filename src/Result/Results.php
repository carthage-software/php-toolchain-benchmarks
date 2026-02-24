<?php

declare(strict_types=1);

namespace CarthageSoftware\ToolChainBenchmarks\Result;

use CarthageSoftware\ToolChainBenchmarks\Configuration\Project;
use CarthageSoftware\ToolChainBenchmarks\Profiler\Profile\CommandProfile;
use Psl\Str;
use Psl\Vec;

final class Results
{
    /**
     * @var list<array{project: Project, category: string, tool: string, mean: float, stddev: float, min: float, max: float, peakMb: float, timedOut: bool}>
     */
    private array $entries = [];

    /**
     * Record a profiled result (timing + memory combined).
     */
    public function addResult(Project $project, string $category, string $tool, CommandProfile $profile): void
    {
        $perf = $profile->performance;

        $this->entries[] = [
            'project' => $project,
            'category' => $category,
            'tool' => $tool,
            'mean' => $perf->mean()->getTotalSeconds(),
            'stddev' => $perf->stddev()->getTotalSeconds(),
            'min' => $perf->min()->getTotalSeconds(),
            'max' => $perf->max()->getTotalSeconds(),
            'peakMb' => $profile->memory->peakMb,
            'timedOut' => false,
        ];
    }

    /**
     * Record a timed-out tool so it still appears in reports.
     */
    public function addTimedOut(Project $project, string $category, string $tool): void
    {
        $this->entries[] = [
            'project' => $project,
            'category' => $category,
            'tool' => $tool,
            'mean' => 0.0,
            'stddev' => 0.0,
            'min' => 0.0,
            'max' => 0.0,
            'peakMb' => 0.0,
            'timedOut' => true,
        ];
    }

    /**
     * @return list<array{project: string, category: string, entries: list<array{tool: string, raw_tool: string, mean: float, stddev: float, min: float, max: float, memory: string, raw_memory: null|float, rel: string, raw_rel: float, timed_out: bool}>}>
     */
    public function getReportData(): array
    {
        $groups = $this->buildGroups();

        return Vec\map(Vec\values($groups), static function (array $group): array {
            $group['entries'] = WinnerAnnotator::annotate($group['entries']);

            return $group;
        });
    }

    /**
     * @return array<string, array{project: string, category: string, entries: list<array{tool: string, raw_tool: string, mean: float, stddev: float, min: float, max: float, memory: string, raw_memory: null|float, rel: string, raw_rel: float, timed_out: bool}>}>
     */
    private function buildGroups(): array
    {
        /** @var array<string, array{project: string, category: string, entries: list<array{tool: string, raw_tool: string, mean: float, stddev: float, min: float, max: float, memory: string, raw_memory: null|float, rel: string, raw_rel: float, timed_out: bool}>}> $groups */
        $groups = [];
        foreach ($this->entries as $t) {
            $groupKey = $t['project']->value . ':' . $t['category'];

            $groups[$groupKey] ??= ['project' => $t['project']->value, 'category' => $t['category'], 'entries' => []];
            $groups[$groupKey]['entries'][] = [
                'tool' => $t['tool'],
                'raw_tool' => $t['tool'],
                'mean' => $t['mean'],
                'stddev' => $t['stddev'],
                'min' => $t['min'],
                'max' => $t['max'],
                'memory' => $t['timedOut'] ? '-' : Str\format('%.1f MB', $t['peakMb']),
                'raw_memory' => $t['timedOut'] ? null : $t['peakMb'],
                'rel' => '',
                'raw_rel' => 1.0,
                'timed_out' => $t['timedOut'],
            ];
        }

        return $groups;
    }
}
