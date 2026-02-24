#!/usr/bin/env php
<?php

declare(strict_types=1);

namespace CarthageSoftware\ToolChainBenchmarks;

require_once __DIR__ . '/../vendor/autoload.php';

$exitCode = Application::run($argv);

exit($exitCode);
