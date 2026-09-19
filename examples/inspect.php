<?php

require __DIR__ . '/../vendor/autoload.php';

use Exelearning\ELPParser;

$path = $argv[1] ?? null;

if ($path === null) {
    fwrite(STDERR, "Usage: php examples/inspect.php project.elpx\n");
    exit(2);
}

echo json_encode(
    ELPParser::inspect($path),
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
) . PHP_EOL;
