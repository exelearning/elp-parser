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
$timings = [];
$totalStartedAt = hrtime(true);

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
        $startedAt = hrtime(true);
        $inspection = ELPParser::inspect($path);
        $parser = ELPParser::fromFile($path);
        $elapsedMs = (hrtime(true) - $startedAt) / 1000000;

        if (($inspection['title'] ?? '') !== $parser->getTitle()) {
            throw new RuntimeException(
                'Lightweight inspection and full parsing returned different titles.'
            );
        }

        $parsed++;
        $relativePath = substr(
            $path,
            strlen(rtrim($root, DIRECTORY_SEPARATOR)) + 1
        );
        $timings[] = [
            'path' => $relativePath,
            'milliseconds' => $elapsedMs,
        ];

        fwrite(
            STDOUT,
            sprintf(
                "PASS %s [%s] %.1f ms\n",
                $relativePath,
                $parser->getPackageProfile(),
                $elapsedMs
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

$totalMs = (hrtime(true) - $totalStartedAt) / 1000000;

usort(
    $timings,
    static fn(array $left, array $right): int => $right['milliseconds']
        <=> $left['milliseconds']
);

fwrite(
    STDOUT,
    sprintf(
        "\nUpstream corpus: %d candidates, %d parsed, %d skipped, %d failures, %.1f ms total.\n",
        count($candidates),
        $parsed,
        $skipped,
        count($failures),
        $totalMs
    )
);

if ($timings !== []) {
    fwrite(STDOUT, "Slowest parsed projects:\n");

    foreach (array_slice($timings, 0, 5) as $timing) {
        fwrite(
            STDOUT,
            sprintf(
                "  %.1f ms  %s\n",
                $timing['milliseconds'],
                $timing['path']
            )
        );
    }
}

fwrite(
    STDOUT,
    sprintf(
        "Peak memory: %.1f MiB\n",
        memory_get_peak_usage(true) / 1048576
    )
);

exit($failures === [] ? 0 : 1);
