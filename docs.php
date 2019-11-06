<?php
require 'vendor/autoload.php';

use PhpParser\Error;
use PhpParser\Lexer\Emulative;
use PhpParser\NodeDumper;
use PhpParser\Parser\Php7;

$code = <<<'CODE'
<?php

desc('Creating symlinks for shared files and dirs');
task('deploy:shared', function () {});
CODE;


$parser = new Php7(new Emulative());
try {
    $ast = $parser->parse($code);
} catch (Error $error) {
    echo "Parse error: {$error->getMessage()}\n";
    return;
}

$dumper = new NodeDumper(['dumpComments' => true]);
echo $dumper->dump($ast) . "\n";
