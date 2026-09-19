<?php

/**
 * Tests for package detection helpers and ParserOptions.
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
use Exelearning\ParserOptions;
use RuntimeException;

it(
    'identifies probes and checks supported package files',
    function () {
        $modern = __DIR__ . '/../Fixtures/propiedades.elpx';
        $legacy = __DIR__ . '/../Fixtures/04_La_Ilustracion.elp';
        $invalid = __DIR__ . '/../Fixtures/invalid.jpg';

        expect(ELPParser::supports($modern))->toBeTrue();
        expect(ELPParser::supports($legacy))->toBeTrue();
        expect(ELPParser::supports($invalid))->toBeFalse();

        expect(ELPParser::identify($modern))->toBe('elpx-v4');
        expect(ELPParser::identify($legacy))->toBe('legacy-v2');

        $probe = ELPParser::probe($modern);
        $inspect = ELPParser::inspect($modern);

        expect($probe)->toBe($inspect);
        expect($probe['title'])->toBe('propiedades');
        expect($probe)->not->toHaveKey('pages');
    }
);

it(
    'accepts parser options without breaking legacy archive limit arguments',
    function () {
        $path = __DIR__ . '/../Fixtures/propiedades.elpx';

        $legacyLimits = new ArchiveLimits(maxXmlBytes: 128 * 1024 * 1024);
        $legacyParser = ELPParser::fromFile($path, $legacyLimits);

        expect($legacyParser->getTitle())->toBe('propiedades');
        expect($legacyParser->getOptions()->archiveLimits)->toBe($legacyLimits);

        $options = new ParserOptions(
            archiveLimits: new ArchiveLimits(maxXmlBytes: 128 * 1024 * 1024),
            parseAssets: false,
            collectStrings: false,
            normalizeIdeviceState: false
        );
        $parser = ELPParser::fromFile($path, $options);

        expect($parser->getOptions())->toBe($options);
        expect($parser->getAssets())->toBe([]);
        expect($parser->getAssetsDetailed())->toBe([]);
        expect($parser->getStrings())->toBe([]);
        expect($parser->getIdevices())->not->toBe([]);
        expect($parser->getIdevices()[0]['storagePattern'])->toBe('disabled');
        expect($parser->getIdevices()[0]['data'])->toBe([]);
        expect($parser->getIdevices()[0]['stateDecodeError'])->toBeNull();
    }
);

it(
    'applies parser options to stream content and lightweight inspection factories',
    function () {
        $path = __DIR__ . '/../Fixtures/propiedades.elpx';
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException('Unable to read fixture.');
        }

        $options = new ParserOptions(
            archiveLimits: new ArchiveLimits(maxTotalBytes: 32 * 1024 * 1024),
            parseAssets: false
        );

        $parser = ELPParser::fromContents($contents, 'elpx', $options);
        expect($parser->getOptions())->toBe($options);
        expect($parser->getAssets())->toBe([]);

        $info = ELPParser::inspectContents($contents, 'elpx', $options);
        expect($info['title'])->toBe('propiedades');

        $stream = fopen($path, 'rb');
        if ($stream === false) {
            throw new RuntimeException('Unable to open fixture stream.');
        }

        try {
            $streamParser = ELPParser::fromStream($stream, 'elpx', $options);
            expect($streamParser->getAssets())->toBe([]);
        } finally {
            fclose($stream);
        }

        $stream = fopen($path, 'rb');
        if ($stream === false) {
            throw new RuntimeException('Unable to open fixture stream.');
        }

        try {
            $streamInfo = ELPParser::inspectStream($stream, 'elpx', $options);
            expect($streamInfo['packageProfile'])->toBe('elpx-v4');
        } finally {
            fclose($stream);
        }
    }
);
