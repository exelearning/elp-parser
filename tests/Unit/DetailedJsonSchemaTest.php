<?php

/**
 * Tests for the versioned detailed JSON contract.
 *
 * @category Tests
 * @package  ELPParser
 * @author   INTEF <cedec@educacion.gob.es>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/exelearning/elp-parser
 */

namespace Exelearning\ElpParser\Tests\Unit;

use Exelearning\ELPParser;

it(
    'ships a readable versioned json schema for detailed exports',
    function () {
        expect(ELPParser::getDetailedSchemaVersion())->toBe('1.0');

        $path = ELPParser::getDetailedJsonSchemaPath();
        expect(is_file($path))->toBeTrue();

        $schema = json_decode(
            ELPParser::getDetailedJsonSchema(),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        expect($schema['$schema'])
            ->toBe('https://json-schema.org/draft/2020-12/schema');
        expect($schema['properties']['schemaVersion']['const'])
            ->toBe('1.0');
        expect($schema['properties'])->toHaveKey('pages');
        expect($schema['properties'])->toHaveKey('idevices');
        expect($schema['properties'])->toHaveKey('assets');
        expect($schema['properties'])->toHaveKey('fingerprints');
    }
);

it(
    'adds schema version to detailed arrays and json exports',
    function () {
        $parser = ELPParser::fromFile(
            __DIR__ . '/../Fixtures/propiedades.elpx'
        );

        $detailed = $parser->toDetailedArray();
        expect($detailed['schemaVersion'])->toBe('1.0');

        $json = json_decode(
            $parser->exportDetailedJson(),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        expect($json['schemaVersion'])->toBe('1.0');
        expect($json)->toHaveKey('summary');
        expect($json)->toHaveKey('format');
        expect($json)->toHaveKey('pages');
        expect($json)->toHaveKey('packageManifest');
    }
);
