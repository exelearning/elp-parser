<?php

require __DIR__ . '/../vendor/autoload.php';

use Exelearning\ELPParser;

$path = $argv[1] ?? null;
$destination = $argv[2] ?? null;

if ($path === null || $destination === null) {
    fwrite(STDERR, "Usage: php examples/extract.php project.elpx destination/\n");
    exit(2);
}

ELPParser::fromFile($path)->extract($destination);
