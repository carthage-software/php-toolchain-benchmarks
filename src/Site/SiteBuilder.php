<?php

declare(strict_types=1);

namespace CarthageSoftware\StaticAnalyzersBenchmark\Site;

use CarthageSoftware\StaticAnalyzersBenchmark\Support\Output;
use Psl\File;
use Psl\Filesystem;
use Psl\Iter;
use Psl\Json;
use Psl\Str;
use Psl\Type;
use Psl\Vec;

/**
 * Builds a single self-contained HTML dashboard from all benchmark results.
 *
 * Reads every results/YYYYMMDD-HHMMSS/report.json, aggregates them, and
 * produces results/index.html with inline CSS/JS and Canvas line charts.
 */
final readonly class SiteBuilder
{
    /**
     * @param non-empty-string $rootDir
     */
    public static function run(string $rootDir): int
    {
        $resultsDir = $rootDir . '/results';

        Output::section('Building results page');

        $runs = self::loadAllReports($resultsDir);
        if ($runs === []) {
            Output::error('No benchmark results found in results/');
            return 1;
        }

        $html = self::buildHtml($runs);
        $outputPath = $resultsDir . '/index.html';

        if (Filesystem\is_file($outputPath)) {
            Filesystem\delete_file($outputPath);
        }

        File\write($outputPath, $html, File\WriteMode::MustCreate);

        Output::success(Str\format('Built results page: %s (%d run(s))', $outputPath, Iter\count($runs)));

        return 0;
    }

    /**
     * @param non-empty-string $resultsDir
     *
     * @return list<array{generated: string, dir: string, projects: array<string, array<string, list<array{analyzer: string, mean: float, stddev: float, min: float, max: float, memory_mb: null|float, relative: float}>>>}>
     */
    private static function loadAllReports(string $resultsDir): array
    {
        if (!Filesystem\is_directory($resultsDir)) {
            return [];
        }

        $runs = [];
        foreach (Filesystem\read_directory($resultsDir) as $entry) {
            if (!Filesystem\is_directory($entry)) {
                continue;
            }

            $reportPath = $entry . '/report.json';
            if (!Filesystem\is_file($reportPath)) {
                continue;
            }

            $report = self::parseReport($reportPath);
            if ($report !== null) {
                $runs[] = $report;
            }
        }

        return Vec\sort($runs, static fn(array $a, array $b): int => $a['generated'] <=> $b['generated']);
    }

    /**
     * @param non-empty-string $path
     *
     * @return null|array{generated: string, dir: string, projects: array<string, array<string, list<array{analyzer: string, mean: float, stddev: float, min: float, max: float, memory_mb: null|float, relative: float}>>>}
     */
    private static function parseReport(string $path): ?array
    {
        $entryType = Type\shape([
            'analyzer' => Type\string(),
            'mean' => Type\float(),
            'stddev' => Type\float(),
            'min' => Type\float(),
            'max' => Type\float(),
            'memory_mb' => Type\nullable(Type\float()),
            'relative' => Type\float(),
        ]);

        $type = Type\shape([
            'generated' => Type\string(),
            'projects' => Type\dict(Type\string(), Type\dict(Type\string(), Type\vec($entryType))),
        ]);

        try {
            $data = Json\typed(File\read($path), $type);
        } catch (\Throwable) {
            Output::warn(Str\format('Skipping malformed report: %s', $path));

            return null;
        }

        return [
            'generated' => $data['generated'],
            'dir' => Filesystem\get_filename(Filesystem\get_directory($path)),
            'projects' => $data['projects'],
        ];
    }

    /**
     * @param non-empty-list<array{generated: string, dir: string, projects: array<string, array<string, list<array{analyzer: string, mean: float, stddev: float, min: float, max: float, memory_mb: null|float, relative: float}>>>}> $runs
     *
     * @return non-empty-string
     */
    private static function buildHtml(array $runs): string
    {
        $css = self::buildCss();
        $js = self::buildJs($runs);

        $latestRun = $runs[Iter\count($runs) - 1];
        $projects = Vec\keys($latestRun['projects']);
        /** @var list<string> $categories */
        $categories = [];
        foreach ($latestRun['projects'] as $cats) {
            foreach (Vec\keys($cats) as $cat) {
                if (Iter\contains($categories, $cat)) {
                    continue;
                }

                $categories[] = $cat;
            }
        }

        $projectOptions = Str\join(Vec\map($projects, static fn(string $p): string => Str\format(
            '<option value="%s">%s</option>',
            $p,
            $p,
        )), '');

        $categoryOptions = Str\join(Vec\map($categories, static fn(string $c): string => Str\format(
            '<option value="%s">%s</option>',
            $c,
            $c,
        )), '');

        $runCount = (string) Iter\count($runs);
        $generated = $latestRun['generated'];

        return <<<HTML
            <!DOCTYPE html>
            <html lang="en">
            <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>PHP Static Analyzer Benchmarks — Mago, PHPStan, Psalm, Phan</title>
            <meta name="description" content="Performance benchmarks comparing PHP static analyzers: Mago, PHPStan, Psalm, and Phan. Execution time and memory usage across real-world projects.">
            <meta name="keywords" content="PHP, static analysis, benchmark, Mago, PHPStan, Psalm, Phan, performance, comparison">
            <meta name="robots" content="index, follow">
            <link rel="canonical" href="https://carthage-software.github.io/static-analyzers-benchmarks/">
            <meta property="og:type" content="website">
            <meta property="og:title" content="PHP Static Analyzer Benchmarks">
            <meta property="og:description" content="Performance benchmarks comparing PHP static analyzers: Mago, PHPStan, Psalm, and Phan. Execution time and memory usage across real-world projects.">
            <meta property="og:url" content="https://carthage-software.github.io/static-analyzers-benchmarks/">
            <meta name="twitter:card" content="summary">
            <meta name="twitter:title" content="PHP Static Analyzer Benchmarks">
            <meta name="twitter:description" content="Performance benchmarks comparing PHP static analyzers: Mago, PHPStan, Psalm, and Phan.">
            <style>{$css}</style>
            </head>
            <body>
            <h1>PHP Static Analyzer Benchmarks</h1>
            <p class="meta">Latest: {$generated} &middot; {$runCount} run(s) &middot; <a href="https://github.com/carthage-software/static-analyzers-benchmarks">Source</a></p>
            <nav>
            <label>Project <select id="project-select">{$projectOptions}</select></label>
            <label>Category <select id="category-select">{$categoryOptions}</select></label>
            </nav>
            <section>
            <h2>Latest Run</h2>
            <div id="overview-content"></div>
            </section>
            <section>
            <h2>Version Comparison</h2>
            <div id="version-charts"></div>
            </section>
            <section>
            <h2>Run-over-Run Diff</h2>
            <div id="run-diff"></div>
            </section>
            <section>
            <h2>Peak Memory (Latest Uncached)</h2>
            <div id="memory-content"></div>
            </section>
            <section>
            <h2>All Runs</h2>
            <div id="details-content"></div>
            </section>
            <section>
            <h2>Methodology</h2>
            <p>All analyzers are run on the same machine, during the same session, under identical conditions. Execution time is measured using <a href="https://github.com/sharkdp/hyperfine">hyperfine</a> with multiple runs and warmup iterations. Peak memory usage is calculated by polling RSS across the entire process tree (including child processes) during an uncached run. Results are sorted by mean execution time.</p>
            </section>
            <script>{$js}</script>
            </body>
            </html>
            HTML;
    }

    /**
     * @return non-empty-string
     */
    private static function buildCss(): string
    {
        return <<<'CSS'
            *{margin:0;padding:0;box-sizing:border-box}
            body{font-family:'SF Mono','Menlo','Consolas',monospace;font-size:13px;line-height:1.5;color:#111;background:#fff;max-width:960px;margin:0 auto;padding:20px}
            h1{font-size:16px;font-weight:bold;border-bottom:1px solid #111;padding-bottom:4px;margin-bottom:4px}
            h2{font-size:14px;font-weight:bold;margin:20px 0 8px;border-bottom:1px solid #ddd;padding-bottom:2px}
            .meta{color:#666;font-size:12px;margin-bottom:12px}
            .meta a{color:#444;text-decoration:underline}
            nav{margin:0 0 16px;display:flex;gap:12px}
            nav label{font-size:12px;color:#666}
            select{font-family:inherit;font-size:12px;padding:2px 4px;border:1px solid #999;background:#fff}
            table{border-collapse:collapse;width:100%;margin:8px 0}
            th,td{border:1px solid #ccc;padding:3px 8px;text-align:right;white-space:nowrap}
            th{background:#f5f5f5;font-weight:bold;text-align:left}
            td:first-child{text-align:left}
            tr.winner td{font-weight:bold}
            .bar-group{margin:12px 0 20px}
            .bar-group h3{font-size:12px;margin:0 0 6px;font-weight:bold}
            .bar-row{display:flex;align-items:center;margin:2px 0;font-size:11px}
            .bar-label{width:180px;text-align:right;padding-right:8px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
            .bar-track{flex:1;height:18px;background:#f5f5f5;border:1px solid #ddd;position:relative}
            .bar-fill{height:100%;background:#888}
            .bar-value{padding-left:6px;min-width:70px;white-space:nowrap}
            .winner-bar .bar-fill{background:#333}
            .winner-bar .bar-label{font-weight:bold}
            .diff-positive{color:#666}
            .diff-negative{color:#111;font-weight:bold}
            details{margin:4px 0;border:1px solid #eee;padding:4px 8px}
            details[open]{padding-bottom:8px}
            summary{cursor:pointer;font-weight:bold;padding:4px 0;font-size:12px}
            .muted{color:#999;font-size:12px}
            .sub-heading{font-size:12px;font-weight:bold;margin:8px 0 4px;color:#444}
            section{margin-bottom:24px}
            section p{margin:4px 0;line-height:1.6}
            section a{color:#444;text-decoration:underline}
            code{background:#f5f5f5;padding:1px 4px;border:1px solid #ddd;font-size:12px}
            CSS;
    }

    /**
     * @param non-empty-list<array{generated: string, dir: string, projects: array<string, array<string, list<array{analyzer: string, mean: float, stddev: float, min: float, max: float, memory_mb: null|float, relative: float}>>>}> $runs
     *
     * @return non-empty-string
     */
    private static function buildJs(array $runs): string
    {
        $jsData = Json\encode($runs);

        return <<<JS
            (function(){
            "use strict";
            var DATA={$jsData};
            var projSel=document.getElementById("project-select");
            var catSel=document.getElementById("category-select");
            function curProj(){return projSel.value}
            function curCat(){return catSel.value}
            projSel.addEventListener("change",render);
            catSel.addEventListener("change",render);

            function fmt(v){return v<10?v.toFixed(3):v<100?v.toFixed(2):v.toFixed(1)}

            function getEntries(run,proj,cat){
                return((run.projects[proj]||{})[cat])||[];
            }

            function renderOverview(){
                var run=DATA[DATA.length-1];
                var entries=getEntries(run,curProj(),curCat());
                if(!entries.length){document.getElementById("overview-content").innerHTML='<p class="muted">No data.</p>';return}
                var h='<table><tr><th>Analyzer</th><th>Mean</th><th>&plusmn; StdDev</th><th>Min</th><th>Max</th><th>Memory</th><th>Relative</th></tr>';
                for(var i=0;i<entries.length;i++){
                    var e=entries[i];
                    var w=e.relative<=1.0;
                    h+='<tr'+(w?' class="winner"':'')+'>';
                    h+='<td>'+e.analyzer+'</td>';
                    h+='<td>'+fmt(e.mean)+'s</td>';
                    h+='<td>&plusmn; '+fmt(e.stddev)+'s</td>';
                    h+='<td>'+fmt(e.min)+'s</td>';
                    h+='<td>'+fmt(e.max)+'s</td>';
                    h+='<td>'+(e.memory_mb!==null?e.memory_mb.toFixed(1)+' MB':'-')+'</td>';
                    h+='<td>'+(w?'1.0x':'x'+e.relative.toFixed(1))+'</td>';
                    h+='</tr>';
                }
                h+='</table>';
                document.getElementById("overview-content").innerHTML=h;
            }

            function parseTool(analyzer){
                var m=analyzer.match(/^(.+?)\s+([\d].+)$/);
                if(!m)return{tool:analyzer,version:""};
                return{tool:m[1],version:m[2]};
            }

            function renderVersionCharts(){
                var run=DATA[DATA.length-1];
                var entries=getEntries(run,curProj(),curCat());
                if(!entries.length){document.getElementById("version-charts").innerHTML='<p class="muted">No data.</p>';return}

                var tools={};
                for(var i=0;i<entries.length;i++){
                    var p=parseTool(entries[i].analyzer);
                    if(!tools[p.tool])tools[p.tool]=[];
                    tools[p.tool].push({version:p.version,mean:entries[i].mean,analyzer:entries[i].analyzer});
                }

                var h='';
                var toolNames=Object.keys(tools).sort();
                for(var ti=0;ti<toolNames.length;ti++){
                    var name=toolNames[ti];
                    var vers=tools[name];
                    if(vers.length<2){continue}
                    vers.sort(function(a,b){return a.version.localeCompare(b.version,undefined,{numeric:true})});
                    var maxMean=0;
                    for(var vi=0;vi<vers.length;vi++){if(vers[vi].mean>maxMean)maxMean=vers[vi].mean}
                    var minMean=vers[0].mean;
                    for(var vi2=0;vi2<vers.length;vi2++){if(vers[vi2].mean<minMean)minMean=vers[vi2].mean}

                    h+='<div class="bar-group"><h3>'+name+'</h3>';
                    for(var vi3=0;vi3<vers.length;vi3++){
                        var v=vers[vi3];
                        var pct=(v.mean/maxMean*100).toFixed(1);
                        var isWinner=v.mean===minMean;
                        h+='<div class="bar-row'+(isWinner?' winner-bar':'')+'">';
                        h+='<div class="bar-label">'+v.analyzer+'</div>';
                        h+='<div class="bar-track"><div class="bar-fill" style="width:'+pct+'%"></div></div>';
                        h+='<div class="bar-value">'+fmt(v.mean)+'s</div>';
                        h+='</div>';
                    }
                    h+='</div>';
                }

                if(!h){h='<p class="muted">No multi-version tools for this combination.</p>'}
                document.getElementById("version-charts").innerHTML=h;
            }

            function renderRunDiff(){
                if(DATA.length<2){document.getElementById("run-diff").innerHTML='<p class="muted">Need at least 2 runs to show diff.</p>';return}
                var latest=DATA[DATA.length-1];
                var prev=DATA[DATA.length-2];
                var proj=curProj(),cat=curCat();
                var cur=getEntries(latest,proj,cat);
                var old=getEntries(prev,proj,cat);
                if(!cur.length||!old.length){document.getElementById("run-diff").innerHTML='<p class="muted">No comparable data.</p>';return}

                var oldMap={};
                for(var i=0;i<old.length;i++){oldMap[old[i].analyzer]=old[i]}

                var rows=[];
                for(var j=0;j<cur.length;j++){
                    var c=cur[j];
                    var o=oldMap[c.analyzer];
                    if(!o){continue}
                    var delta=c.mean-o.mean;
                    var pct=((delta/o.mean)*100);
                    rows.push({analyzer:c.analyzer,prev:o.mean,curr:c.mean,delta:delta,pct:pct});
                }

                if(!rows.length){document.getElementById("run-diff").innerHTML='<p class="muted">No matching analyzers between runs.</p>';return}

                var h='<p class="muted">Comparing: '+latest.generated+' vs '+prev.generated+'</p>';
                h+='<table><tr><th>Analyzer</th><th>Previous</th><th>Current</th><th>Delta</th><th>Change</th></tr>';
                for(var r=0;r<rows.length;r++){
                    var row=rows[r];
                    var cls=row.delta<=0?'diff-negative':'diff-positive';
                    var sign=row.delta<=0?'':'+';
                    h+='<tr><td>'+row.analyzer+'</td>';
                    h+='<td>'+fmt(row.prev)+'s</td>';
                    h+='<td>'+fmt(row.curr)+'s</td>';
                    h+='<td class="'+cls+'">'+sign+fmt(row.delta)+'s</td>';
                    h+='<td class="'+cls+'">'+sign+row.pct.toFixed(1)+'%</td>';
                    h+='</tr>';
                }
                h+='</table>';
                document.getElementById("run-diff").innerHTML=h;
            }

            function renderMemory(){
                var run=DATA[DATA.length-1];
                var entries=getEntries(run,curProj(),"Uncached").filter(function(e){return e.memory_mb!==null});
                if(!entries.length){document.getElementById("memory-content").innerHTML='<p class="muted">No memory data.</p>';return}
                entries.sort(function(a,b){return a.memory_mb-b.memory_mb});
                var minMem=entries[0].memory_mb;
                var h='<table><tr><th>Analyzer</th><th>Peak Memory (MB)</th><th>Relative</th></tr>';
                for(var i=0;i<entries.length;i++){
                    var e=entries[i];
                    var rel=(e.memory_mb/minMem).toFixed(1);
                    var w=e.memory_mb===minMem;
                    h+='<tr'+(w?' class="winner"':'')+'><td>'+e.analyzer+'</td>';
                    h+='<td>'+e.memory_mb.toFixed(1)+'</td>';
                    h+='<td>'+(w?'1.0x':'x'+rel)+'</td></tr>';
                }
                h+='</table>';
                document.getElementById("memory-content").innerHTML=h;
            }

            function buildTable(entries){
                var h='<table><tr><th>Analyzer</th><th>Mean</th><th>StdDev</th><th>Min</th><th>Max</th><th>Memory</th><th>Rel</th></tr>';
                for(var i=0;i<entries.length;i++){
                    var e=entries[i];
                    h+='<tr><td>'+e.analyzer+'</td>';
                    h+='<td>'+fmt(e.mean)+'s</td>';
                    h+='<td>'+fmt(e.stddev)+'s</td>';
                    h+='<td>'+fmt(e.min)+'s</td>';
                    h+='<td>'+fmt(e.max)+'s</td>';
                    h+='<td>'+(e.memory_mb!==null?e.memory_mb.toFixed(1)+' MB':'-')+'</td>';
                    h+='<td>'+(e.relative<=1.0?'1.0x':'x'+e.relative.toFixed(1))+'</td></tr>';
                }
                return h+'</table>';
            }

            function renderDetails(){
                var h='';
                for(var i=DATA.length-1;i>=0;i--){
                    var run=DATA[i];
                    h+='<details>';
                    h+='<summary>'+run.generated+' ('+run.dir+')</summary>';
                    var projs=Object.keys(run.projects).sort();
                    for(var pi=0;pi<projs.length;pi++){
                        var cats=Object.keys(run.projects[projs[pi]]).sort();
                        for(var ci=0;ci<cats.length;ci++){
                            h+='<div class="sub-heading">'+projs[pi]+' / '+cats[ci]+'</div>';
                            h+=buildTable(run.projects[projs[pi]][cats[ci]]);
                        }
                    }
                    h+='</details>';
                }
                document.getElementById("details-content").innerHTML=h;
            }

            function render(){renderOverview();renderVersionCharts();renderRunDiff();renderMemory();renderDetails()}
            render();
            })();
            JS;
    }
}
