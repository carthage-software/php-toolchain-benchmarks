<?php

return [
    'target_php_version' => '8.3',
    'minimum_target_php_version' => '8.3',
    'directory_list' => [
        '{{WORKSPACE}}/src',
        '{{WORKSPACE}}/examples',
        '{{WORKSPACE}}/vendor',
    ],
    'exclude_analysis_directory_list' => [
        '{{WORKSPACE}}/vendor',
    ],
    'cache_directory' => '{{CACHE_DIR}}',
    'analyze_signature_compatibility' => true,
    'allow_missing_properties' => false,
    'null_casts_as_any_type' => false,
    'scalar_implicit_cast' => false,
    'processes' => 10,
    'plugins' => [
        'AlwaysReturnPlugin',
        'DollarDollarPlugin',
        'DuplicateArrayKeyPlugin',
        'DuplicateExpressionPlugin',
        'PregRegexCheckerPlugin',
        'PrintfCheckerPlugin',
        'SleepCheckerPlugin',
        'UnreachableCodePlugin',
        'UseReturnValuePlugin',
        'EmptyStatementListPlugin',
        'LoopVariableReusePlugin',
    ],
];
