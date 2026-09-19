<?php

/**
 * Tests for stream and in-memory project inputs.
 *
 * @category Tests
 * @package  ELPParser
 * @author   INTEF <cedec@educacion.gob.es>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/exelearning/elp-parser
 */

namespace Exelearning\ElpParser\Tests\Unit;

use Exelearning\Archive\ArchiveLimits;
use Exelearning\ELPParser;
use Exelearning\Exception\ResourceLimitException;
use RuntimeException;

it(
    'parses a project from in-memory bytes',
    function () {
        $path = __DIR__ . '/../Fixtures/propiedades.elpx';
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException('Unable to read fixture.');
        }

        $parser = ELPParser::fromContents($contents, 'elpx');

        expect($parser->getTitle())->toBe('propiedades');
        expect($parser->getSourceExtension())->toBe('elpx');
        expect($parser->getPackageProfile())->toBe('elpx-v4');
    }
);

it(
    'parses a project from a readable stream',
    function () {
        $stream = fopen(__DIR__ . '/../Fixtures/04_La_Ilustracion.elp', 'rb');

        if ($stream === false) {
            throw new RuntimeException('Unable to open fixture stream.');
        }

        try {
            $parser = ELPParser::fromStream($stream, 'elp');

            expect($parser->getFormatFamily())->toBe('legacy');
            expect($parser->getSourceExtension())->toBe('elp');
            expect($parser->getVersion())->toBe(2);
        } finally {
            fclose($stream);
        }
    }
);

it(
    'supports lightweight inspection from streams and contents',
    function () {
        $path = __DIR__ . '/../Fixtures/propiedades.elpx';
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException('Unable to read fixture.');
        }

        $fromContents = ELPParser::inspectContents($contents, 'elpx');

        $stream = fopen($path, 'rb');
        if ($stream === false) {
            throw new RuntimeException('Unable to open fixture stream.');
        }

        try {
            $fromStream = ELPParser::inspectStream($stream, 'elpx');
        } finally {
            fclose($stream);
        }

        expect($fromContents['title'])->toBe('propiedades');
        expect($fromStream['title'])->toBe('propiedades');
        expect($fromContents['packageProfile'])->toBe('elpx-v4');
        expect($fromStream['formatVersion'])->toBe('2.0');
    }
);

it(
    'applies configured limits while spooling project input',
    function () {
        $path = __DIR__ . '/../Fixtures/propiedades.elpx';
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException('Unable to read fixture.');
        }

        $limits = new ArchiveLimits(maxTotalBytes: 128);

        expect(
            fn() => ELPParser::fromContents($contents, 'elpx', $limits)
        )->toThrow(ResourceLimitException::class);
    }
);
