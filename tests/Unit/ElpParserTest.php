<?php

/**
 * Unit tests for ELPParser class
 *
 * @category Tests
 * @package  ELPParser
 * @author   INTEF <cedec@educacion.gob.es>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/exelearning/elp-parser
 */

namespace Exelearning\ElpParser\Tests\Unit;

require_once __DIR__ . '/../../src/ElpParser.php';

use Exelearning\ELPParser;

it(
    'can parse a version 2 ELP file', function () {
        $elpFile = __DIR__ . '/fixtures/exe2-parada-2-riesgos-de-la-ruta-itinerario-para-la-empleabilidad-i.elp';
    
        // Ensure the test file exists
        expect(file_exists($elpFile))->toBeTrue('Test ELP file for version 2 not found');
    
        $parser = ELPParser::fromFile($elpFile);
    
        // Check version detection
        expect($parser->getVersion())->toBe(2);
    
        // Check extracted strings
        $strings = $parser->getStrings();
        expect($strings)->toBeArray();
        expect(count($strings))->toBeGreaterThan(0);
    
        // Optionally, check for some expected content
        expect($strings)->toContain('Some expected text from version 2 file');
    }
);

it(
    'can parse a version 3 ELP file', function () {
        $elpFile = __DIR__ . '/fixtures/exe3-ipe1_parada2.elp';
    
        // Ensure the test file exists
        expect(file_exists($elpFile))->toBeTrue('Test ELP file for version 3 not found');
    
        $parser = ELPParser::fromFile($elpFile);
    
        // Check version detection
        expect($parser->getVersion())->toBe(3);
    
        // Check extracted strings
        $strings = $parser->getStrings();
        expect($strings)->toBeArray();
        expect(count($strings))->toBeGreaterThan(0);
    
        // Optionally, check for some expected content
        expect($strings)->toContain('Some expected text from version 3 file');
    }
);

it(
    'can extract an ELP file', function () {
        $elpFile = __DIR__ . '/fixtures/exe2-parada-2-riesgos-de-la-ruta-itinerario-para-la-empleabilidad-i.elp';
        $extractPath = __DIR__ . '/fixtures/extracted_v2';
    
        // Ensure the test file exists
        expect(file_exists($elpFile))->toBeTrue('Test ELP file for extraction not found');
    
        $parser = ELPParser::fromFile($elpFile);
    
        // Attempt to extract
        $parser->extract($extractPath);
    
        // Check that extraction was successful
        expect(is_dir($extractPath))->toBeTrue('Extraction directory was not created');
        expect(file_exists($extractPath . '/content.xml'))->toBeTrue('content.xml not found in extracted files');
    
        // Clean up extracted files (optional)
        $files = glob($extractPath . '/*');
        foreach ($files as $file) {
            unlink($file);
        }
        rmdir($extractPath);
    }
);

it(
    'throws an exception for invalid ELP file', function () {
        $invalidFile = __DIR__ . '/fixtures/invalid.png';
    
        // Create an invalid file for testing
        file_put_contents($invalidFile, 'This is not a valid ELP file');
    
        // Expect an exception when trying to parse
        expect(fn() => ELPParser::fromFile($invalidFile))
        ->toThrow(Exception::class, 'No content XML found');
    
        // Clean up invalid file
        unlink($invalidFile);
    }
);

