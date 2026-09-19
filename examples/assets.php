<?php

require __DIR__ . '/../vendor/autoload.php';

use Exelearning\ELPParser;

$path = $argv[1] ?? null;

if ($path === null) {
    fwrite(STDERR, "Usage: php examples/assets.php project.elpx\n");
    exit(2);
}

$parser = ELPParser::fromFile($path);

print_r($parser->getAssetsDetailed());
print_r($parser->getMissingAssets());
print_r($parser->getOrphanAssets());
