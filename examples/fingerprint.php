<?php

require __DIR__ . '/../vendor/autoload.php';

use Exelearning\ELPParser;

$path = $argv[1] ?? null;

if ($path === null) {
    fwrite(STDERR, "Usage: php examples/fingerprint.php project.elpx\n");
    exit(2);
}

$parser = ELPParser::fromFile($path);

echo 'Archive: ' . $parser->getArchiveFingerprint() . PHP_EOL;
echo 'Content: ' . $parser->getContentFingerprint() . PHP_EOL;
