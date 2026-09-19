<?php

require __DIR__ . '/../vendor/autoload.php';

use Exelearning\ELPParser;

$path = $argv[1] ?? null;

if ($path === null) {
    fwrite(STDERR, "Usage: php examples/basic.php project.elpx\n");
    exit(2);
}

$parser = ELPParser::fromFile($path);

echo $parser->getTitle() . PHP_EOL;
echo $parser->getPackageProfile() . PHP_EOL;

foreach ($parser->getPages() as $page) {
    echo '- ' . ($page['title'] ?? '') . PHP_EOL;
}
