<?php

declare(strict_types=1);

namespace CarthageSoftware\ToolChainBenchmarks\Site;

use Psl\Iter;
use Psl\Json;
use Psl\Str;
use Psl\Vec;

/**
 * Renders the benchmark HTML dashboard with inline CSS and JS.
 */
final readonly class HtmlRenderer
{
    /**
     * @param array{
     *     "aggregation-date": string,
     *     kinds: array<string, list<string>>,
     *     projects: array<string, array<string, array<string, list<array{date: string, mean: float, stddev: float, min: float, max: float, memory_mb: null|float, relative: float, timed_out: bool}>>>>
     * } $latest
     * @param non-empty-list<array{generated: string, dir: string, kinds: array<string, list<string>>, projects: array<string, array<string, list<array{tool: string, mean: float, stddev: float, min: float, max: float, memory_mb: null|float, relative: float, timed_out: bool}>>>}> $runs
     *
     * @return non-empty-string
     */
    public static function render(array $latest, array $runs): string
    {
        $css = self::buildCss();
        $js = self::buildJs($latest, $runs);

        $projectOptions = Str\join(Vec\map(Vec\keys($latest['projects']), static fn(string $p): string => Str\format(
            '<option value="%s">%s</option>',
            $p,
            $p,
        )), '');

        $runCount = (string) Iter\count($runs);
        $generated = $latest['aggregation-date'];

        return <<<HTML
            <!DOCTYPE html>
            <html lang="en">
            <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>PHP Toolchain Benchmarks — Formatters, Linters, Analyzers</title>
            <meta name="description" content="Performance benchmarks comparing PHP tools: formatters (Mago Fmt, Pretty PHP), linters (Mago Lint, PHP-CS-Fixer, PHPCS), and analyzers (Mago, PHPStan, Psalm, Phan). Execution time and memory usage across real-world projects.">
            <meta name="keywords" content="PHP, benchmark, formatter, linter, static analysis, Mago, PHPStan, Psalm, Phan, PHP-CS-Fixer, PHPCS, Pretty PHP, performance, comparison">
            <meta name="robots" content="index, follow">
            <link rel="canonical" href="https://carthage-software.github.io/static-analyzers-benchmarks/">
            <meta property="og:type" content="website">
            <meta property="og:title" content="PHP Toolchain Benchmarks">
            <meta property="og:description" content="Performance benchmarks comparing PHP formatters, linters, and static analyzers across real-world projects.">
            <meta property="og:url" content="https://carthage-software.github.io/static-analyzers-benchmarks/">
            <meta name="twitter:card" content="summary">
            <meta name="twitter:title" content="PHP Toolchain Benchmarks">
            <meta name="twitter:description" content="Performance benchmarks comparing PHP formatters, linters, and analyzers.">
            <style>{$css}</style>
            </head>
            <body>
            <h1>PHP Toolchain Benchmarks</h1>
            <p class="meta">Latest: {$generated} &middot; {$runCount} run(s) &middot; <a href="https://github.com/carthage-software/static-analyzers-benchmarks">Source</a></p>
            <section>
            <h2>Methodology</h2>
            <p>This project benchmarks PHP <strong>formatters</strong> (<a href="https://github.com/carthage-software/mago">Mago Fmt</a>, <a href="https://github.com/lkrms/pretty-php">Pretty PHP</a>), <strong>linters</strong> (<a href="https://github.com/carthage-software/mago">Mago Lint</a>, <a href="https://github.com/PHP-CS-Fixer/PHP-CS-Fixer">PHP-CS-Fixer</a>, <a href="https://github.com/PHPCSStandards/PHP_CodeSniffer">PHPCS</a>), and <strong>static analyzers</strong> (<a href="https://github.com/carthage-software/mago">Mago</a>, <a href="https://github.com/phpstan/phpstan">PHPStan</a>, <a href="https://github.com/vimeo/psalm">Psalm</a>, <a href="https://github.com/phan/phan">Phan</a>) against real-world open-source codebases.</p>
            <p>All tools are run on the same machine, during the same session, under identical conditions. Every tool is configured at its <strong>strictest settings</strong> to ensure maximum work. Execution time is measured using a built-in profiler with multiple runs. Peak memory usage is calculated by polling RSS across the entire process tree (including child processes). For static analyzers, both cold (uncached) and hot (cached) runs are measured. A <strong>5-minute timeout</strong> is enforced on every run; tools marked as "Timed out" could not complete within this limit. Results are sorted by mean execution time.</p>
            </section>
            <nav>
            <div class="tabs" id="kind-tabs">
            <button class="tab" data-kind="Analyzers">Analyzers</button>
            <button class="tab" data-kind="Formatters">Formatters</button>
            <button class="tab" data-kind="Linters">Linters</button>
            </div>
            <label>Project <select id="project-select">{$projectOptions}</select></label>
            </nav>
            <div id="categories-content"></div>
            <div id="memory-content"></div>
            <section>
            <h2>Run-over-Run Diff</h2>
            <div id="run-diff"></div>
            </section>
            <section>
            <h2>All Runs</h2>
            <div id="details-content"></div>
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
            h3{font-size:13px;font-weight:bold;margin:16px 0 6px;color:#333}
            .meta{color:#666;font-size:12px;margin-bottom:12px}
            .meta a{color:#444;text-decoration:underline}
            nav{margin:0 0 16px;display:flex;gap:16px;align-items:center}
            nav label{font-size:12px;color:#666}
            select{font-family:inherit;font-size:12px;padding:2px 4px;border:1px solid #999;background:#fff}
            .tabs{display:flex;gap:0}
            .tab{font-family:inherit;font-size:12px;padding:4px 12px;border:1px solid #999;background:#fff;cursor:pointer;color:#666}
            .tab:not(:first-child){border-left:0}
            .tab.active{background:#111;color:#fff;border-color:#111}
            table{border-collapse:collapse;width:100%;margin:8px 0}
            th,td{border:1px solid #ccc;padding:3px 8px;text-align:right;white-space:nowrap}
            th{background:#f5f5f5;font-weight:bold;text-align:left}
            td:first-child{text-align:left}
            tr.winner td{font-weight:bold}
            .bar-group{margin:12px 0 20px}
            .bar-group h4{font-size:12px;margin:0 0 6px;font-weight:bold}
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
            .kind-section{margin:16px 0 24px}
            CSS;
    }

    /**
     * @param array{
     *     "aggregation-date": string,
     *     kinds: array<string, list<string>>,
     *     projects: array<string, array<string, array<string, list<array{date: string, mean: float, stddev: float, min: float, max: float, memory_mb: null|float, relative: float, timed_out: bool}>>>>
     * } $latest
     * @param non-empty-list<array{generated: string, dir: string, kinds: array<string, list<string>>, projects: array<string, array<string, list<array{tool: string, mean: float, stddev: float, min: float, max: float, memory_mb: null|float, relative: float, timed_out: bool}>>>}> $runs
     *
     * @return non-empty-string
     */
    private static function buildJs(array $latest, array $runs): string
    {
        $jsLatest = Json\encode($latest);
        $jsRuns = Json\encode($runs);

        return <<<JS
            (function(){
            "use strict";
            var LATEST={$jsLatest};
            var RUNS={$jsRuns};
            var ALL_KINDS=LATEST.kinds;
            var projSel=document.getElementById("project-select");
            var activeKind="Analyzers";
            var tabBtns=document.querySelectorAll("#kind-tabs .tab");

            function curProj(){return projSel.value}
            function curCats(){return ALL_KINDS[activeKind]||[]}

            function readParams(){
                var p=new URLSearchParams(window.location.search);
                if(p.get("project")){for(var i=0;i<projSel.options.length;i++){if(projSel.options[i].value===p.get("project")){projSel.value=p.get("project");break}}}
                if(p.get("kind")&&ALL_KINDS[p.get("kind")]){activeKind=p.get("kind")}
            }
            function writeParams(){
                var p=new URLSearchParams();
                p.set("project",curProj());
                p.set("kind",activeKind);
                history.replaceState(null,"","?"+p.toString());
            }
            function updateTabs(){
                for(var i=0;i<tabBtns.length;i++){
                    tabBtns[i].classList.toggle("active",tabBtns[i].getAttribute("data-kind")===activeKind);
                }
            }

            readParams();updateTabs();
            projSel.addEventListener("change",function(){writeParams();render()});
            for(var ti=0;ti<tabBtns.length;ti++){
                tabBtns[ti].addEventListener("click",function(){activeKind=this.getAttribute("data-kind");updateTabs();writeParams();render()});
            }

            function fmt(v){return v<10?v.toFixed(3):v<100?v.toFixed(2):v.toFixed(1)}

            /* ── LATEST data helpers (aggregated, tool-keyed) ── */

            function getLatestEntries(proj,cat){
                var toolMap=((LATEST.projects[proj]||{})[cat])||{};
                var toolNames=Object.keys(toolMap);
                if(!toolNames.length)return null;
                var entries=[];
                for(var i=0;i<toolNames.length;i++){
                    var runs=toolMap[toolNames[i]];
                    if(!runs.length)continue;
                    var r=runs[0];
                    entries.push({tool:toolNames[i],date:r.date,mean:r.mean,stddev:r.stddev,min:r.min,max:r.max,memory_mb:r.memory_mb,timed_out:r.timed_out,relative:0});
                }
                if(!entries.length)return null;
                var valid=entries.filter(function(e){return!e.timed_out});
                var minMean=Infinity;
                for(var k=0;k<valid.length;k++){if(valid[k].mean<minMean)minMean=valid[k].mean}
                for(var k=0;k<entries.length;k++){
                    if(entries[k].timed_out)continue;
                    entries[k].relative=minMean>0?Math.round((entries[k].mean/minMean)*10)/10:1.0;
                }
                entries.sort(function(a,b){
                    if(a.timed_out&&b.timed_out)return 0;
                    if(a.timed_out)return 1;
                    if(b.timed_out)return-1;
                    return a.mean-b.mean;
                });
                return{entries:entries};
            }

            /* ── RUNS data helpers (individual runs, array-based) ── */

            function getRunEntries(run,proj,cat){
                return((run.projects[proj]||{})[cat])||[];
            }

            function getTwoLatestRuns(proj,cat){
                var found=[];
                for(var i=RUNS.length-1;i>=0;i--){
                    var e=getRunEntries(RUNS[i],proj,cat);
                    if(e.length){found.push(RUNS[i]);if(found.length===2)break}
                }
                if(found.length<2)return null;
                return{latest:found[0],prev:found[1]};
            }

            /* ── Render functions ── */

            function renderOverviewTable(entries){
                var h='<table><tr><th>Tool</th><th>Mean</th><th>&plusmn; StdDev</th><th>Min</th><th>Max</th><th>Memory</th><th>Relative</th></tr>';
                for(var i=0;i<entries.length;i++){
                    var e=entries[i];
                    if(e.timed_out){
                        h+='<tr><td>'+e.tool+'</td><td colspan="5" style="text-align:center;color:#999">Timed out</td><td>-</td></tr>';
                        continue;
                    }
                    var w=e.relative<=1.0;
                    h+='<tr'+(w?' class="winner"':'')+'>';
                    h+='<td>'+e.tool+'</td>';
                    h+='<td>'+fmt(e.mean)+'s</td>';
                    h+='<td>&plusmn; '+fmt(e.stddev)+'s</td>';
                    h+='<td>'+fmt(e.min)+'s</td>';
                    h+='<td>'+fmt(e.max)+'s</td>';
                    h+='<td>'+(e.memory_mb!==null?e.memory_mb.toFixed(1)+' MB':'-')+'</td>';
                    h+='<td>'+(w?'&#x1F680; 1.0x':'x'+e.relative.toFixed(1))+'</td>';
                    h+='</tr>';
                }
                return h+'</table>';
            }

            function parseTool(name){
                var m=name.match(/^(.+?)\s+([\d].+)$/);
                if(!m)return{tool:name,version:""};
                return{tool:m[1],version:m[2]};
            }

            function renderVersionBars(entries){
                var tools={};
                for(var i=0;i<entries.length;i++){
                    if(entries[i].timed_out)continue;
                    var p=parseTool(entries[i].tool);
                    if(!tools[p.tool])tools[p.tool]=[];
                    tools[p.tool].push({version:p.version,mean:entries[i].mean,name:entries[i].tool});
                }

                var h='';
                var toolNames=Object.keys(tools).sort();
                for(var tti=0;tti<toolNames.length;tti++){
                    var tn=toolNames[tti];
                    var vers=tools[tn];
                    if(vers.length<2){continue}
                    vers.sort(function(a,b){return a.version.localeCompare(b.version,undefined,{numeric:true})});
                    var maxMean=0;
                    for(var vi=0;vi<vers.length;vi++){if(vers[vi].mean>maxMean)maxMean=vers[vi].mean}
                    var minMean=vers[0].mean;
                    for(var vi2=0;vi2<vers.length;vi2++){if(vers[vi2].mean<minMean)minMean=vers[vi2].mean}

                    h+='<div class="bar-group"><h4>'+tn+'</h4>';
                    for(var vi3=0;vi3<vers.length;vi3++){
                        var v=vers[vi3];
                        var pct=(v.mean/maxMean*100).toFixed(1);
                        var isWinner=v.mean===minMean;
                        h+='<div class="bar-row'+(isWinner?' winner-bar':'')+'">';
                        h+='<div class="bar-label">'+v.name+'</div>';
                        h+='<div class="bar-track"><div class="bar-fill" style="width:'+pct+'%"></div></div>';
                        h+='<div class="bar-value">'+fmt(v.mean)+'s</div>';
                        h+='</div>';
                    }
                    h+='</div>';
                }
                return h;
            }

            var CAT_LABELS={"Formatter":"Performance","Linter":"Performance","Cold":"Performance (Cold)","Hot":"Performance (Hot)"};
            function catLabel(cat){return CAT_LABELS[cat]||cat}

            function renderCategories(){
                var proj=curProj();
                var cats=curCats();
                var h='';

                for(var ci=0;ci<cats.length;ci++){
                    var cat=cats[ci];
                    var result=getLatestEntries(proj,cat);
                    if(!result)continue;
                    h+='<h3>'+catLabel(cat)+'</h3>';
                    h+=renderOverviewTable(result.entries);
                    var bars=renderVersionBars(result.entries);
                    if(bars){h+='<details><summary>Version Comparison</summary>'+bars+'</details>'}
                }

                if(!h){h='<p class="muted">No '+activeKind.toLowerCase()+' data for this project.</p>'}
                document.getElementById("categories-content").innerHTML=h;
            }

            function renderMemory(){
                var proj=curProj();
                var cats=curCats();
                var cat=cats[0];
                var result=getLatestEntries(proj,cat);
                var h='';

                if(result){
                    var entries=result.entries.filter(function(e){return e.memory_mb!==null&&!e.timed_out});
                    if(entries.length){
                        entries.sort(function(a,b){return a.memory_mb-b.memory_mb});
                        var minMem=entries[0].memory_mb;
                        h+='<h2>Memory</h2>';
                        h+='<table><tr><th>Tool</th><th>Peak Memory (MB)</th><th>Relative</th></tr>';
                        for(var i=0;i<entries.length;i++){
                            var e=entries[i];
                            var rel=(e.memory_mb/minMem).toFixed(1);
                            var w=e.memory_mb===minMem;
                            h+='<tr'+(w?' class="winner"':'')+'><td>'+e.tool+'</td>';
                            h+='<td>'+e.memory_mb.toFixed(1)+'</td>';
                            h+='<td>'+(w?'&#x1F389; 1.0x':'x'+rel)+'</td></tr>';
                        }
                        h+='</table>';
                    }
                }

                document.getElementById("memory-content").innerHTML=h;
            }

            function renderRunDiff(){
                var proj=curProj();
                var cats=curCats();
                var h='';
                for(var ci=0;ci<cats.length;ci++){
                    var cat=cats[ci];
                    var pair=getTwoLatestRuns(proj,cat);
                    if(!pair)continue;
                    var cur=getRunEntries(pair.latest,proj,cat);
                    var old=getRunEntries(pair.prev,proj,cat);
                    var oldMap={};
                    for(var i=0;i<old.length;i++){var n=old[i].tool||"";if(!old[i].timed_out)oldMap[n]=old[i]}
                    var rows=[];
                    for(var j=0;j<cur.length;j++){
                        var c=cur[j];
                        if(c.timed_out)continue;
                        var cn=c.tool||"";
                        var o=oldMap[cn];
                        if(!o)continue;
                        var delta=c.mean-o.mean;
                        var pct=((delta/o.mean)*100);
                        rows.push({name:cn,prev:o.mean,curr:c.mean,delta:delta,pct:pct});
                    }
                    if(!rows.length)continue;
                    h+='<h3>'+catLabel(cat)+'</h3>';
                    h+='<p class="muted">Comparing: '+pair.latest.generated+' vs '+pair.prev.generated+'</p>';
                    h+='<table><tr><th>Tool</th><th>Previous</th><th>Current</th><th>Delta</th><th>Change</th></tr>';
                    for(var r=0;r<rows.length;r++){
                        var row=rows[r];
                        var cls=row.delta<=0?'diff-negative':'diff-positive';
                        var sign=row.delta<=0?'':'+';
                        h+='<tr><td>'+row.name+'</td>';
                        h+='<td>'+fmt(row.prev)+'s</td>';
                        h+='<td>'+fmt(row.curr)+'s</td>';
                        h+='<td class="'+cls+'">'+sign+fmt(row.delta)+'s</td>';
                        h+='<td class="'+cls+'">'+sign+row.pct.toFixed(1)+'%</td></tr>';
                    }
                    h+='</table>';
                }
                if(!h){h='<p class="muted">No comparable data between runs.</p>'}
                document.getElementById("run-diff").innerHTML=h;
            }

            function buildTable(entries){
                var h='<table><tr><th>Tool</th><th>Mean</th><th>StdDev</th><th>Min</th><th>Max</th><th>Memory</th><th>Rel</th></tr>';
                for(var i=0;i<entries.length;i++){
                    var e=entries[i];
                    var name=e.tool||"";
                    if(e.timed_out){
                        h+='<tr><td>'+name+'</td><td colspan="5" style="text-align:center;color:#999">Timed out</td><td>-</td></tr>';
                        continue;
                    }
                    h+='<tr><td>'+name+'</td>';
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
                var cats=curCats();
                var h='';
                for(var i=RUNS.length-1;i>=0;i--){
                    var run=RUNS[i];
                    var proj=curProj();
                    var runHtml='';
                    for(var ci=0;ci<cats.length;ci++){
                        var entries=getRunEntries(run,proj,cats[ci]);
                        if(!entries.length)continue;
                        runHtml+='<div class="sub-heading">'+catLabel(cats[ci])+'</div>';
                        runHtml+=buildTable(entries);
                    }
                    if(!runHtml)continue;
                    h+='<details>';
                    h+='<summary>'+run.generated+' ('+run.dir+')</summary>';
                    h+=runHtml;
                    h+='</details>';
                }
                if(!h){h='<p class="muted">No historical data.</p>'}
                document.getElementById("details-content").innerHTML=h;
            }

            function render(){renderCategories();renderMemory();renderRunDiff();renderDetails()}
            render();writeParams();
            })();
            JS;
    }
}
