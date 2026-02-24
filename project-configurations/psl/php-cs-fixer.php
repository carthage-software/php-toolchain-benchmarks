<?php

$finder = new PhpCsFixer\Finder()
    ->in('{{WORKSPACE}}')
    ->path(['src/', 'examples/'])
    ->exclude(['vendor']);

return new PhpCsFixer\Config()
    ->setRules([
        '@PhpCsFixer' => true,
        '@PhpCsFixer:risky' => true,
    ])
    ->setRiskyAllowed(true)
    ->setParallelConfig(PhpCsFixer\Runner\Parallel\ParallelConfigFactory::detect())
    ->setFinder($finder);
