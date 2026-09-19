<?php

require __DIR__ . '/../vendor/autoload.php';

use Exelearning\ELPParser;

$path = $argv[1] ?? null;

if ($path === null) {
    fwrite(STDERR, "Usage: php examples/validate.php project.elpx\n");
    exit(2);
}

$result = ELPParser::fromFile($path)->validate();

echo json_encode(
    $result,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
) . PHP_EOL;

exit($result['valid'] ? 0 : 1);
