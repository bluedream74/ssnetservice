<?php

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->notPath('bootstrap')
    ->notPath('storage')
    ->notPath('vendor')
    ->in(getcwd())
    ->name('*.php')
    ->notName('*.blade.php')
    ->notName('index.php')
    ->notName('server.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

return (new Config())
    ->setFinder($finder)
    ->setRules([
        '@PhpCsFixer' => true,
        'blank_line_before_statement' => [
            'statements' => ['return'],
        ],
        'concat_space' => [
            'spacing' => 'one',
        ],
        'increment_style' => [
            'style' => 'post',
        ],
        'multiline_whitespace_before_semicolons' => [
            'strategy' => 'no_multi_line',
        ],
        'phpdoc_no_empty_return' => false,
        'yoda_style' => [
            'equal' => false,
            'identical' => false,
            'less_and_greater' => false,
        ],
    ]);
