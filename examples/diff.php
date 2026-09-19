<?php

require __DIR__ . '/../vendor/autoload.php';

use Exelearning\ELPParser;

$left = $argv[1] ?? null;
$right = $argv[2] ?? null;

if ($left === null || $right === null) {
    fwrite(STDERR, "Usage: php examples/diff.php old.elpx new.elpx\n");
    exit(2);
}

$diff = ELPParser::fromFile($left)->diff(
    ELPParser::fromFile($right)
);

echo json_encode(
    $diff,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
) . PHP_EOL;
