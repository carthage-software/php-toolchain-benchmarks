<?php

return [
    'target_php_version' => '8.0',
    'minimum_target_php_version' => '8.0',
    'directory_list' => [
        '{{WORKSPACE}}/src',
        '{{WORKSPACE}}/tests',
        '{{WORKSPACE}}/vendor',
    ],
    'exclude_analysis_directory_list' => [
        '{{WORKSPACE}}/src/js',
        '{{WORKSPACE}}/vendor',
    ],
    'cache_directory' => '{{CACHE_DIR}}',
    'analyze_signature_compatibility' => true,
    'allow_missing_properties' => false,
    'null_casts_as_any_type' => false,
    'scalar_implicit_cast' => false,
    'processes' => 10,
];
