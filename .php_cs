<?php

$finder = PhpCsFixer\Finder::create()->in([
    __DIR__.'/src',
]);

return PhpCsFixer\Config::create()
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR2' => true,
        'array_syntax' => ['syntax' => 'long'],
        'strict_param' => true,
    ])
    ->setFinder($finder);
