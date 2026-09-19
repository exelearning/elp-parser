<?php

/**
 * TemporaryProjectFile.php
 *
 * PHP Version 8.0
 *
 * @category Support
 * @package  Exelearning
 * @author   INTEF <cedec@educacion.gob.es>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/exelearning/elp-parser
 */

namespace Exelearning\Support;

use Exelearning\Exception\ElpParserException;
use Exelearning\Exception\ResourceLimitException;

/**
 * Spool project bytes to a bounded temporary file for ZipArchive-based parsing.
 */
final class TemporaryProjectFile
{
    private const CHUNK_BYTES = 1048576;

    /**
     * Copy a readable stream to a temporary project file.
     *
     * @param mixed  $stream    Readable PHP stream resource.
     * @param string $extension Desired temporary file extension.
     * @param int    $maxBytes  Maximum compressed input bytes.
     *
     * @return string
     */
    public static function fromStream(
        mixed $stream,
        string $extension,
        int $maxBytes
    ): string {
        if (!is_resource($stream)) {
            throw new ElpParserException('Project stream must be a readable resource.');
        }

        $metadata = stream_get_meta_data($stream);
        if (($metadata['mode'] ?? '') === '') {
            throw new ElpParserException('Unable to inspect project stream.');
        }

        $path = self::createPath($extension);
        $target = fopen($path, 'wb');

        if ($target === false) {
            @unlink($path);
            throw new ElpParserException('Unable to create temporary project file.');
        }

        $total = 0;

        try {
            while (!feof($stream)) {
                $chunk = fread($stream, self::CHUNK_BYTES);

                if ($chunk === false) {
                    throw new ElpParserException('Unable to read project stream.');
                }

                if ($chunk === '') {
                    continue;
                }

                $total += strlen($chunk);

                if ($total > $maxBytes) {
                    throw new ResourceLimitException(
                        'Project input exceeds the configured maximum input size.'
                    );
                }

                self::writeAll($target, $chunk);
            }
        } catch (\Throwable $exception) {
            fclose($target);
            @unlink($path);
            throw $exception;
        }

        fclose($target);

        return $path;
    }

    /**
     * Write in-memory bytes to a bounded temporary project file.
     *
     * @param string $contents  Project bytes.
     * @param string $extension Desired temporary file extension.
     * @param int    $maxBytes  Maximum compressed input bytes.
     *
     * @return string
     */
    public static function fromContents(
        string $contents,
        string $extension,
        int $maxBytes
    ): string {
        if (strlen($contents) > $maxBytes) {
            throw new ResourceLimitException(
                'Project input exceeds the configured maximum input size.'
            );
        }

        $path = self::createPath($extension);

        if (file_put_contents($path, $contents) === false) {
            @unlink($path);
            throw new ElpParserException('Unable to write temporary project file.');
        }

        return $path;
    }

    /**
     * Normalize an extension for a temporary project filename.
     *
     * @param string $extension Requested extension.
     *
     * @return string
     */
    private static function normalizeExtension(string $extension): string
    {
        $extension = strtolower(trim($extension));
        $extension = ltrim($extension, '.');
        $extension = preg_replace('/[^a-z0-9]+/', '', $extension) ?? '';

        return $extension !== '' ? $extension : 'elpx';
    }

    /**
     * Create a temporary project path with the requested extension.
     *
     * @param string $extension Requested extension.
     *
     * @return string
     */
    private static function createPath(string $extension): string
    {
        $basePath = tempnam(sys_get_temp_dir(), 'elp-parser-');

        if ($basePath === false) {
            throw new ElpParserException('Unable to allocate temporary project file.');
        }

        $path = $basePath . '.' . self::normalizeExtension($extension);

        if (!rename($basePath, $path)) {
            @unlink($basePath);
            throw new ElpParserException('Unable to prepare temporary project file.');
        }

        return $path;
    }

    /**
     * Write an entire string to a stream, handling partial writes.
     *
     * @param resource $stream Target stream.
     * @param string   $data   Bytes to write.
     *
     * @return void
     */
    private static function writeAll($stream, string $data): void
    {
        $offset = 0;
        $length = strlen($data);

        while ($offset < $length) {
            $written = fwrite($stream, substr($data, $offset));

            if ($written === false || $written === 0) {
                throw new ElpParserException('Unable to write temporary project file.');
            }

            $offset += $written;
        }
    }
}
