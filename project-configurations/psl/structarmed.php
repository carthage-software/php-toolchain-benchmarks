<?php

declare(strict_types=1);

use Boundwize\StructArmed\Architecture;

return Architecture::define()
    ->cacheDirectory('{{CACHE_DIR}}')
    // The Source layer pins the scanned paths; without it StructArmed scans every composer.json PSR-4 path.
    ->layer('Source', ['src/', 'examples/'])
    ->layer('Library', 'src/')
    ->layer('Examples', 'examples/')
    ->ruleset([
        'Library' => [],
        'Examples' => ['Library'],
    ]);
