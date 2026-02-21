# PHP Static Analyzer Benchmarks

Reproducible benchmark suite for PHP static analyzers: **Mago**, **PHPStan**, **Psalm**, and **Phan**.

Measures execution time (via [hyperfine](https://github.com/sharkdp/hyperfine)) and peak memory (via process tree RSS polling) across real-world PHP projects.

## Benchmark Categories

| Category        | Description                                       | Analyzers            |
| --------------- | ------------------------------------------------- | -------------------- |
| **Uncached**    | Cold start, no cache                              | All                  |
| **Incremental** | Cache invalidation after file change (4 variants) | PHPStan, Psalm, Phan |

Incremental variants:

- **No change** - cache hit, no file modification
- **Touch** - `touch` the file (mtime only)
- **No-op change** - append a no-op statement
- **Logic change** - append a real code change

## Target Projects

| Project                                                             | Size  | Type                |
| ------------------------------------------------------------------- | ----- | ------------------- |
| [azjezz/psl](https://github.com/azjezz/psl)                         | Small | Well-typed library  |
| [wordpress-develop](https://github.com/WordPress/wordpress-develop) | Large | Untyped application |

## Prerequisites

- PHP 8.5+
- [Composer](https://getcomposer.org)
- [hyperfine](https://github.com/sharkdp/hyperfine) (`brew install hyperfine`)
- [just](https://github.com/casey/just) (optional, for development tasks)

## Usage

```bash
# Install dependencies
composer install

# Setup: clone projects, install analyzer tools, process configs
./bin/benchmark setup

# Run full benchmark
./bin/benchmark run

# Filter by project, analyzer, or category
./bin/benchmark run --project psl --runs 5
./bin/benchmark run --analyzer mago --category uncached --runs 3 --warmup 1
```

### CLI Options

| Option             | Default | Description                                            |
| ------------------ | ------- | ------------------------------------------------------ |
| `--runs N`         | 10      | Number of benchmark runs                               |
| `--warmup N`       | 2       | Number of warmup runs                                  |
| `--project NAME`   | all     | Filter by project: `psl`, `wordpress`                  |
| `--analyzer NAME`  | all     | Filter by analyzer: `mago`, `phpstan`, `psalm`, `phan` |
| `--category NAME`  | all     | Filter by category: `uncached`, `incremental`          |
| `--skip-stability` | false   | Skip the CPU stability check                           |

## Results

Results are saved to `results/YYYYMMDD-HHMMSS/` with:

- `report.md` - Final report in Markdown format
- `report.json` - Structured JSON report
- `summary.md` - Combined hyperfine results
- `<project>/` - Per-project hyperfine JSON/Markdown and raw memory output

The final report highlights the fastest analyzer per category with a trophy and shows relative performance (e.g., x5.2 slower than the winner).

## Adding a New Project

1. Add a case to the `Project` enum in `src/Configuration/Project.php` with repo URL, ref, and incremental file path.
2. Create config templates in `project-configurations/<slug>/` with `{{WORKSPACE}}` and `{{CACHE_DIR}}` placeholders:
   - `mago.toml`, `phpstan.neon`, `psalm.xml`, `phan.php`
3. Run `./bin/benchmark setup`.

## Adding a New Analyzer

1. Add a case to the `Analyzer` enum in `src/Configuration/Analyzer.php`.
2. Implement `getCommand()`, `getUncachedCommand()`, `getClearCacheCommand()`, and `supportsCaching()`.
3. Add the analyzer's config filename in `getConfigFilename()`.
4. Add the package to `ToolInstaller.php`.
5. Add config templates in each `project-configurations/<project>/` directory.
6. Run `./bin/benchmark setup`.

## Development

```bash
# Check code style, linting, and static analysis
just check

# Auto-fix issues
just fix
```

## License

MIT
