# List all available tasks.
list:
    @just --list

# Check code style, linting and static analysis.
check:
    ./vendor/bin/mago fmt --check
    ./vendor/bin/mago lint
    ./vendor/bin/mago analyze

# Automatically fix code style, linting and static analysis issues. Use with --unsafe to allow unsafe fixes.
fix:
    ./vendor/bin/mago lint --fix --unsafe
    ./vendor/bin/mago analyze --fix --unsafe
    ./vendor/bin/mago fmt

# Setup the benchmark environment. This will install all dependencies and prepare the environment for running benchmarks.
setup:
    bin/benchmark setup

# Run the benchmarks. This will execute all benchmark tests and report the results.
benchmark:
    bin/benchmark run
