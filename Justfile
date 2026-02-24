# List all available tasks.
list:
    @just --list

# Check code style, linting and static analysis.
check:
    mago fmt --check
    mago lint
    mago analyze

# Automatically fix code style, linting and static analysis issues. Use with --unsafe to allow unsafe fixes.
fix:
    mago lint --fix --unsafe
    mago analyze --fix --unsafe
    mago fmt

# Setup the benchmark environment. This will install all dependencies and prepare the environment for running benchmarks.
setup:
    php src/main.php setup

# Run the benchmarks. This will execute all benchmark tests and report the results.
benchmark:
    php src/main.php run

# Build the benchmark webpage. This will compile the benchmark results into a web-friendly format for easy viewing and sharing.
build:
    php src/main.php build
