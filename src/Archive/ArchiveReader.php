<?php

/**
 * ArchiveReader.php
 *
 * PHP Version 8.0
 *
 * @category Parser
 * @package  Exelearning
 * @author   INTEF <cedec@educacion.gob.es>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/exelearning/elp-parser
 */

namespace Exelearning\Archive;

use Exelearning\Exception\InvalidArchiveException;
use Exelearning\Exception\ResourceLimitException;
use Exelearning\Exception\UnsafeArchiveException;
use ZipArchive;

/**
 * Read and safely extract ZIP-based eXeLearning project files.
 *
 * @category Parser
 * @package  Exelearning
 * @author   INTEF <cedec@educacion.gob.es>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/exelearning/elp-parser
 */
class ArchiveReader
{
    private const ZIP_OPSYS_UNIX = 3;

    /**
     * @param string        $filePath Project archive path.
     * @param ArchiveLimits $limits   Resource limits.
     */
    public function __construct(
        private string $filePath,
        private ArchiveLimits $limits
    ) {
    }

    /**
     * Validate the archive and return its entry names.
     *
     * @return array<int, string>
     */
    public function inspect(): array
    {
        $zip = $this->openArchive();

        try {
            if ($zip->numFiles > $this->limits->maxEntries) {
                throw new ResourceLimitException('ZIP archive contains too many entries.');
            }

            $entries = [];
            $seen = [];
            $totalBytes = 0;

            for ($index = 0; $index < $zip->numFiles; $index++) {
                $stat = $zip->statIndex($index);
                if (!is_array($stat) || !isset($stat['name'])) {
                    throw new InvalidArchiveException('Unable to inspect ZIP entry.');
                }

                $entryName = (string) $stat['name'];
                $this->assertSafeEntryName($entryName);
                $this->assertNotSymlink($zip, $index, $entryName);

                if (isset($seen[$entryName])) {
                    throw new InvalidArchiveException('Duplicate ZIP entry detected: ' . $entryName);
                }

                $seen[$entryName] = true;
                $entries[] = $entryName;

                if (str_ends_with($entryName, '/')) {
                    continue;
                }

                $size = max(0, (int) ($stat['size'] ?? 0));
                $compressedSize = max(0, (int) ($stat['comp_size'] ?? 0));

                if ($size > $this->limits->maxEntryBytes) {
                    throw new ResourceLimitException('ZIP entry exceeds the configured size limit: ' . $entryName);
                }

                $totalBytes += $size;
                if ($totalBytes > $this->limits->maxTotalBytes) {
                    throw new ResourceLimitException('ZIP archive exceeds the configured total size limit.');
                }

                if ($size > 0) {
                    $ratio = $size / max(1, $compressedSize);
                    if ($ratio > $this->limits->maxCompressionRatio) {
                        throw new ResourceLimitException(
                            'ZIP entry exceeds the configured compression ratio limit: ' . $entryName
                        );
                    }
                }
            }

            return $entries;
        } finally {
            $zip->close();
        }
    }

    /**
     * Read one archive entry with a hard byte limit.
     *
     * @param string   $entryName Entry name.
     * @param int|null $maxBytes  Optional byte limit.
     *
     * @return string
     */
    public function readEntry(string $entryName, ?int $maxBytes = null): string
    {
        $limit = $maxBytes ?? $this->limits->maxEntryBytes;
        $zip = $this->openArchive();

        try {
            $index = $zip->locateName($entryName);
            if ($index === false) {
                throw new InvalidArchiveException('ZIP entry not found: ' . $entryName);
            }

            $stat = $zip->statIndex($index);
            if (is_array($stat) && (int) ($stat['size'] ?? 0) > $limit) {
                throw new ResourceLimitException('ZIP entry exceeds the configured read limit: ' . $entryName);
            }

            $stream = $zip->getStream($entryName);
            if ($stream === false) {
                throw new InvalidArchiveException('Unable to read ZIP entry: ' . $entryName);
            }

            try {
                return $this->readStreamWithLimit($stream, $limit, $entryName);
            } finally {
                fclose($stream);
            }
        } finally {
            $zip->close();
        }
    }

    /**
     * Extract all entries while preserving configured resource limits.
     *
     * @param string $destinationPath Destination directory.
     *
     * @return void
     */
    public function extract(string $destinationPath): void
    {
        $this->inspect();

        if (!file_exists($destinationPath) && !mkdir($destinationPath, 0755, true) && !is_dir($destinationPath)) {
            throw new InvalidArchiveException('Unable to create destination directory.');
        }

        $destinationRoot = realpath($destinationPath);
        if ($destinationRoot === false) {
            throw new InvalidArchiveException('Unable to resolve destination directory.');
        }

        $zip = $this->openArchive();
        $totalWritten = 0;

        try {
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $entryName = $zip->getNameIndex($index);
                if ($entryName === false) {
                    continue;
                }

                $this->assertSafeEntryName($entryName);
                $this->assertNotSymlink($zip, $index, $entryName);

                $targetPath = $destinationRoot
                    . DIRECTORY_SEPARATOR
                    . str_replace('/', DIRECTORY_SEPARATOR, $entryName);

                if (str_ends_with($entryName, '/')) {
                    $this->createSafeDirectory($targetPath, $destinationRoot);
                    continue;
                }

                $targetDir = dirname($targetPath);
                $this->createSafeDirectory($targetDir, $destinationRoot);

                if (is_link($targetPath)) {
                    throw new UnsafeArchiveException('Unsafe extraction target detected: ' . $entryName);
                }

                $stream = $zip->getStream($entryName);
                if ($stream === false) {
                    throw new InvalidArchiveException('Unable to read ZIP entry: ' . $entryName);
                }

                $output = fopen($targetPath, 'wb');
                if ($output === false) {
                    fclose($stream);
                    throw new InvalidArchiveException('Unable to extract ZIP entry: ' . $entryName);
                }

                $entryWritten = 0;

                try {
                    while (!feof($stream)) {
                        $chunk = fread($stream, 1048576);
                        if ($chunk === false) {
                            throw new InvalidArchiveException('Unable to read ZIP entry: ' . $entryName);
                        }

                        if ($chunk === '') {
                            continue;
                        }

                        $entryWritten += strlen($chunk);
                        $totalWritten += strlen($chunk);

                        if ($entryWritten > $this->limits->maxEntryBytes) {
                            throw new ResourceLimitException(
                                'ZIP entry exceeds the configured size limit: ' . $entryName
                            );
                        }

                        if ($totalWritten > $this->limits->maxTotalBytes) {
                            throw new ResourceLimitException(
                                'ZIP archive exceeds the configured total size limit.'
                            );
                        }

                        if (fwrite($output, $chunk) === false) {
                            throw new InvalidArchiveException('Unable to extract ZIP entry: ' . $entryName);
                        }
                    }
                } finally {
                    fclose($stream);
                    fclose($output);
                }
            }
        } finally {
            $zip->close();
        }
    }

    /**
     * Open the configured ZIP archive.
     *
     * @return ZipArchive
     */
    private function openArchive(): ZipArchive
    {
        if (!file_exists($this->filePath)) {
            throw new InvalidArchiveException('File does not exist.');
        }

        $zip = new ZipArchive();
        if ($zip->open($this->filePath) !== true) {
            throw new InvalidArchiveException('The file is not a valid ZIP file.');
        }

        return $zip;
    }

    /**
     * Validate an archive path before any filesystem operation.
     *
     * @param string $entryName ZIP entry name.
     *
     * @return void
     */
    private function assertSafeEntryName(string $entryName): void
    {
        if (
            $entryName === ''
            || str_contains($entryName, "\0")
            || str_starts_with($entryName, '/')
            || preg_match('/^[A-Za-z]:[\/\\\\]/', $entryName) === 1
        ) {
            throw new UnsafeArchiveException('Unsafe ZIP entry detected: ' . $entryName);
        }

        foreach (preg_split('#[\/\\\\]+#', $entryName) ?: [] as $segment) {
            if ($segment === '..') {
                throw new UnsafeArchiveException('Unsafe ZIP entry detected: ' . $entryName);
            }
        }
    }

    /**
     * Reject Unix symlink entries stored in ZIP metadata.
     *
     * @param ZipArchive $zip       ZIP archive.
     * @param int        $index     Entry index.
     * @param string     $entryName Entry name.
     *
     * @return void
     */
    private function assertNotSymlink(ZipArchive $zip, int $index, string $entryName): void
    {
        if (!method_exists($zip, 'getExternalAttributesIndex')) {
            return;
        }

        $opsys = 0;
        $attributes = 0;

        if (!$zip->getExternalAttributesIndex($index, $opsys, $attributes)) {
            return;
        }

        $fileType = ($attributes >> 16) & 0170000;
        if ($opsys === self::ZIP_OPSYS_UNIX && $fileType === 0120000) {
            throw new UnsafeArchiveException('Unsafe ZIP symlink detected: ' . $entryName);
        }
    }

    /**
     * Read a stream without allowing it to exceed a byte limit.
     *
     * @param resource $stream    Input stream.
     * @param int      $limit     Maximum bytes.
     * @param string   $entryName Entry name for diagnostics.
     *
     * @return string
     */
    private function readStreamWithLimit($stream, int $limit, string $entryName): string
    {
        $contents = '';

        while (!feof($stream)) {
            $remaining = $limit - strlen($contents) + 1;
            if ($remaining <= 0) {
                break;
            }

            $chunk = fread($stream, min(1048576, $remaining));
            if ($chunk === false) {
                throw new InvalidArchiveException('Unable to read ZIP entry: ' . $entryName);
            }

            $contents .= $chunk;

            if (strlen($contents) > $limit) {
                throw new ResourceLimitException('ZIP entry exceeds the configured read limit: ' . $entryName);
            }
        }

        return $contents;
    }

    /**
     * Create or validate a directory inside the extraction root.
     *
     * @param string $directory       Directory to create or validate.
     * @param string $destinationRoot Canonical extraction root.
     *
     * @return void
     */
    private function createSafeDirectory(string $directory, string $destinationRoot): void
    {
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new InvalidArchiveException('Unable to create directory during extraction.');
        }

        $resolved = realpath($directory);
        if ($resolved === false || !$this->isPathWithinRoot($resolved, $destinationRoot)) {
            throw new UnsafeArchiveException('Unsafe extraction directory detected.');
        }
    }

    /**
     * Check whether a canonical path is located inside the destination root.
     *
     * @param string $path Path to check.
     * @param string $root Extraction root.
     *
     * @return bool
     */
    private function isPathWithinRoot(string $path, string $root): bool
    {
        $path = rtrim($path, DIRECTORY_SEPARATOR);
        $root = rtrim($root, DIRECTORY_SEPARATOR);

        if (PHP_OS_FAMILY === 'Windows') {
            $path = strtolower($path);
            $root = strtolower($root);
        }

        return $path === $root || str_starts_with($path, $root . DIRECTORY_SEPARATOR);
    }

    /**
     * Write the complete buffer to a stream.
     *
     * @param resource $stream    Output stream
     * @param string   $contents  Buffer to write
     * @param string   $entryName Archive entry name for error reporting
     *
     * @return void
     */
    private function writeAll($stream, string $contents, string $entryName): void
    {
        $length = strlen($contents);
        $written = 0;

        while ($written < $length) {
            $result = fwrite($stream, substr($contents, $written));
            if ($result === false || $result === 0) {
                throw new InvalidArchiveException('Unable to extract ZIP entry: ' . $entryName);
            }

            $written += $result;
        }
    }
}
