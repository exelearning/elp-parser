<?php

/**
 * Parse the upstream eXeLearning fixture corpus.
 *
 * Usage:
 *   php tests/upstream-compat.php /path/to/exelearning/test/fixtures
 */

use Exelearning\ELPParser;
use ZipArchive;

require __DIR__ . '/../vendor/autoload.php';

$root = $argv[1] ?? null;

if ($root === null || !is_dir($root)) {
    fwrite(STDERR, "Usage: php tests/upstream-compat.php /path/to/upstream/test/fixtures\n");
    exit(2);
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);

$candidates = [];
foreach ($iterator as $file) {
    if (!$file instanceof SplFileInfo || !$file->isFile()) {
        continue;
    }

    $extension = strtolower($file->getExtension());
    if (!in_array($extension, ['elp', 'elpx'], true)) {
        continue;
    }

    $candidates[] = $file->getPathname();
}

sort($candidates);

$parsed = 0;
$skipped = 0;
$failures = [];

foreach ($candidates as $path) {
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        $skipped++;
        continue;
    }

    $hasProjectXml = $zip->locateName('content.xml') !== false
        || $zip->locateName('contentv3.xml') !== false;
    $zip->close();

    if (!$hasProjectXml) {
        $skipped++;
        continue;
    }

    try {
        $inspection = ELPParser::inspect($path);
        $parser = ELPParser::fromFile($path);

        if (($inspection['title'] ?? '') !== $parser->getTitle()) {
            throw new RuntimeException(
                'Lightweight inspection and full parsing returned different titles.'
            );
        }

        $parsed++;
        fwrite(
            STDOUT,
            sprintf(
                "PASS %s [%s]\n",
                substr($path, strlen(rtrim($root, DIRECTORY_SEPARATOR)) + 1),
                $parser->getPackageProfile()
            )
        );
    } catch (Throwable $exception) {
        $failures[] = [
            'path' => $path,
            'message' => $exception->getMessage(),
            'class' => get_class($exception),
        ];

        fwrite(
            STDERR,
            sprintf(
                "FAIL %s: %s: %s\n",
                $path,
                get_class($exception),
                $exception->getMessage()
            )
        );
    }
}

fwrite(
    STDOUT,
    sprintf(
        "\nUpstream corpus: %d candidates, %d parsed, %d skipped, %d failures.\n",
        count($candidates),
        $parsed,
        $skipped,
        count($failures)
    )
);

exit($failures === [] ? 0 : 1);
