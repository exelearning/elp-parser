<?php

/**
 * Tests for single-entry package access and streaming.
 *
 * @category Tests
 * @package  ELPParser
 * @author   INTEF <cedec@educacion.gob.es>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/exelearning/elp-parser
 */

namespace Exelearning\ElpParser\Tests\Unit;

use Exelearning\ELPParser;
use Exelearning\Exception\InvalidArchiveException;
use Exelearning\Exception\ResourceLimitException;
use RuntimeException;

it(
    'reads copies and extracts individual package entries',
    function () {
        $parser = ELPParser::fromFile(
            __DIR__ . '/../Fixtures/propiedades.elpx'
        );

        $entry = 'content.xml';

        expect($parser->hasEntry($entry))->toBeTrue();
        expect($parser->hasEntry('missing-entry.bin'))->toBeFalse();

        $contents = $parser->getEntryContents($entry);
        expect($contents)->not->toBe('');

        $stream = fopen('php://temp', 'w+b');
        if ($stream === false) {
            throw new RuntimeException('Unable to create temporary stream.');
        }

        try {
            $written = $parser->copyEntryToStream($entry, $stream);
            rewind($stream);
            $copied = stream_get_contents($stream);

            expect($written)->toBe(strlen($contents));
            expect($copied)->toBe($contents);
        } finally {
            fclose($stream);
        }

        $destination = sys_get_temp_dir()
            . '/elp-entry-'
            . uniqid('', true)
            . '/nested/resource.bin';

        try {
            $parser->extractEntry($entry, $destination);

            expect(is_file($destination))->toBeTrue();
            expect(file_get_contents($destination))->toBe($contents);
        } finally {
            @unlink($destination);
            @rmdir(dirname($destination));
            @rmdir(dirname(dirname($destination)));
        }
    }
);

it(
    'enforces entry limits and validates output streams',
    function () {
        $parser = ELPParser::fromFile(
            __DIR__ . '/../Fixtures/propiedades.elpx'
        );
        $entry = 'content.xml';

        expect(fn() => $parser->getEntryContents($entry, 1))
            ->toThrow(ResourceLimitException::class);

        expect(fn() => $parser->copyEntryToStream($entry, 'invalid'))
            ->toThrow(InvalidArchiveException::class);

        $stream = fopen('php://temp', 'w+b');
        if ($stream === false) {
            throw new RuntimeException('Unable to create temporary stream.');
        }

        try {
            expect(fn() => $parser->copyEntryToStream($entry, $stream, 1))
                ->toThrow(ResourceLimitException::class);
        } finally {
            fclose($stream);
        }

        expect(fn() => $parser->getEntryContents('missing-entry.bin'))
            ->toThrow(InvalidArchiveException::class);
    }
);
