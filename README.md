# PHP Toolchain Benchmarks

Reproducible benchmark suite for PHP **formatters**, **linters**, and **static analyzers**. Compares multiple tools and versions side-by-side across real-world open-source codebases.

Execution time is measured using a built-in profiler with multiple runs. Peak memory is calculated by polling RSS across the entire process tree (including child processes).

**Latest results: <https://carthage-software.github.io/static-analyzers-benchmarks/>**

## Tools

| Category       | Tools                                                                                                                                                                            |
| -------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Formatters** | [Mago Fmt](https://github.com/carthage-software/mago), [Pretty PHP](https://github.com/lkrms/pretty-php)                                                                         |
| **Linters**    | [Mago Lint](https://github.com/carthage-software/mago), [PHP-CS-Fixer](https://github.com/PHP-CS-Fixer/PHP-CS-Fixer), [PHPCS](https://github.com/PHPCSStandards/PHP_CodeSniffer) |
| **Analyzers**  | [Mago](https://github.com/carthage-software/mago), [PHPStan](https://github.com/phpstan/phpstan), [Psalm](https://github.com/vimeo/psalm), [Phan](https://github.com/phan/phan)  |

Multiple versions of the same tool can be benchmarked simultaneously (e.g. Mago 1.7.0 through 1.10.0).

## Benchmark Types

| Type          | Applies to | Description                                              |
| ------------- | ---------- | -------------------------------------------------------- |
| **Formatter** | Formatters | Format the entire project                                |
| **Linter**    | Linters    | Lint the entire project                                  |
| **Cold**      | Analyzers  | Cold start, caches cleared before each run               |
| **Hot**       | Analyzers  | Caches warmed once, then measured without cache clearing |

## Target Projects

| Project                                                             | Description         |
| ------------------------------------------------------------------- | ------------------- |
| [azjezz/psl](https://github.com/azjezz/psl)                         | Well-typed library  |
| [wordpress-develop](https://github.com/WordPress/wordpress-develop) | Untyped application |
| [magento/magento2](https://github.com/magento/magento2)             | E-commerce platform |

## Prerequisites

- PHP 8.5+
- [Composer](https://getcomposer.org)
- [just](https://github.com/casey/just) (optional, for development tasks)

## Usage

```bash
# Install dependencies
composer install

# Setup: clone projects, install tools, process configs
just setup

# Run full benchmark
just benchmark

# Filter by project, tool kind, or specific tool
./src/main.php run --project psl --runs 5
./src/main.php run --kind analyzer --tool phpstan --runs 3
./src/main.php run --kind formatter --timeout 10

# Build HTML results dashboard
just build
open results/index.html
```

### CLI Options

| Option              | Default | Description                                                  |
| ------------------- | ------- | ------------------------------------------------------------ |
| `--runs N`          | 10      | Number of benchmark runs per tool                            |
| `--timeout N`       | 5       | Timeout per run in minutes                                   |
| `--project NAME`    | all     | Filter by project: `psl`, `wordpress`, `magento`             |
| `--kind NAME`       | all     | Filter by tool kind: `formatter`, `linter`, `analyzer`       |
| `--tool NAME`       | all     | Filter by tool: `mago-fmt`, `phpstan`, `psalm`, `phan`, etc. |
| `--php-binary PATH` | current | PHP binary to use for PHP-based tools                        |
| `--skip-stability`  | false   | Skip the CPU stability check                                 |

## Results

Each benchmark run produces a `results/YYYYMMDD-HHMMSS/report.json`. The `build` command aggregates all runs into:

- `results/latest.json` — merged data keyed by project, category, and tool name, with the full history of runs per tool
- `results/index.html` — self-contained HTML dashboard with overview tables, version comparison bars, memory usage, run-over-run diffs, and per-run detail tables

The dashboard is automatically deployed to GitHub Pages on every push to `main`.

## Adding a New Project

1. Add a case to the `Project` enum in `src/Configuration/Project.php` with repo URL and ref.
2. Create config templates in `project-configurations/<slug>/` with `{{WORKSPACE}}` and `{{CACHE_DIR}}` placeholders:
   - `mago.toml`, `phpstan.neon`, `psalm-v6.xml`, `phan.php`, `php-cs-fixer.php`, `phpcs.xml`
3. Run `./src/main.php setup`.

## Adding a New Tool Version

1. Add an entry to the `PACKAGES` constant in `src/Setup/ToolInstaller.php`.
2. Add version-specific config templates if needed (e.g. `psalm-v6.xml` for Psalm 6).
3. Run `./src/main.php setup`.

## Adding a New Tool

1. Add a case to the `Tool` enum in `src/Configuration/Tool.php`.
2. Implement all required methods: `getKind()`, `getPackageName()`, `getComposerPackage()`, `getDisplayPrefix()`, `getConfigFilename()`, `supportsCaching()`.
3. Add command building logic in `src/Configuration/CommandBuilder.php`.
4. Add the package to `PACKAGES` in `src/Setup/ToolInstaller.php`.
5. Add config templates in each `project-configurations/<project>/` directory.
6. Run `./src/main.php setup`.

## Profiling a Single Command

`src/profile.php` is a standalone script that demonstrates the built-in profiler. Tool authors who want to investigate or reduce their tool's execution time and memory usage can modify this script to profile any command:

```bash
php src/profile.php
```

Edit the script to change the command, number of runs, or timeout. It outputs mean, stddev, min, max execution time and peak memory — the same measurements used by the benchmark suite.

## Development

```bash
# Check formatting, linting, and static analysis
just check

# Auto-fix formatting
just fix
```

## License

MIT
