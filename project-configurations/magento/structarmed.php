<?php

declare(strict_types=1);

use Boundwize\StructArmed\Architecture;

return Architecture::define()
    ->cacheDirectory('{{CACHE_DIR}}')
    // The Source layer pins the scanned paths; without it StructArmed scans every composer.json PSR-4 path.
    ->layer('Source', ['app/', 'dev/', 'phpserver/', 'setup/', 'pub/'])
    ->layer('App', 'app/')
    ->layer('Dev', 'dev/')
    ->layer('PhpServer', 'phpserver/')
    ->layer('Setup', 'setup/')
    ->layer('Pub', 'pub/')
    ->ruleset([
        'App' => [],
        'PhpServer' => [],
        'Setup' => ['App'],
        'Pub' => ['App', 'Setup'],
        'Dev' => ['App', 'Setup', 'Pub'],
    ]);
