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
use Exception;

it(
    'can parse a version 3 ELP file', function () {
        $elpFile = __DIR__ . '/../Fixtures/exe3-accessibility-revision.elp';
    
        // Ensure the test file exists
        expect(file_exists($elpFile))->toBeTrue('Test ELP file for version 2 not found');
    
        $parser = ELPParser::fromFile($elpFile);
    
        // Check version detection
        expect($parser->getVersion())->toBe(3);

        // Check metadata fields
        expect($parser->getTitle())->toBe('Accessibility revision');
        expect($parser->getDescription())->toContain('vfggg');
        expect($parser->getAuthor())->toBe('The eXeLearning Team');
        expect($parser->getLicense())->toBe('propietary license');
        expect($parser->getLearningResourceType())->toBe('guided reading');
        expect($parser->getLanguage())->toBe('en');

        // Check extracted strings
        $strings = $parser->getStrings();
        expect($strings)->toBeArray();
        expect(count($strings))->toBeGreaterThan(0);
    
        // Optionally, check for some expected content
        // expect($strings)->toContain('Some expected text from version 2 file');
    }
);

it(
    'can parse another version 3 ELP file', function () {
        $elpFile = __DIR__ . '/../Fixtures/exe3-parada-2-riesgos-de-la-ruta-itinerario-para-la-empleabilidad-i.elp';
    
        // Ensure the test file exists
        expect(file_exists($elpFile))->toBeTrue('Test ELP file for version 2 not found');
    
        $parser = ELPParser::fromFile($elpFile);
    
        // Check version detection
        expect($parser->getVersion())->toBe(3);

        // Check metadata fields
        expect($parser->getTitle())->toBe('Parada 2: Riesgos de la ruta | Itinerario para la empleabilidad I');
        expect($parser->getDescription())->toContain('En este REA');
        expect($parser->getAuthor())->toBe('María Cruz García Sanchís y Daniela Gimeno Ruiz para Cedec');
        expect($parser->getLicense())->toBe('propietary license');
        expect($parser->getLearningResourceType())->toBe('real project');
        expect($parser->getLanguage())->toBe('es');

        // Check extracted strings
        $strings = $parser->getStrings();
        expect($strings)->toBeArray();
        expect(count($strings))->toBeGreaterThan(0);
    
        // Optionally, check for some expected content
        // expect($strings)->toContain('Some expected text from version 2 file');
    }
);

it(
    'can parse a version 2 ELP file', function () {
        $elpFile = __DIR__ . '/../Fixtures/exe2-ipe1_parada2.elp';
    
        // Ensure the test file exists
        expect(file_exists($elpFile))->toBeTrue('Test ELP file for version 3 not found');
    
        $parser = ELPParser::fromFile($elpFile);
    
        // Check version detection
        expect($parser->getVersion())->toBe(2);

        // Check metadata fields
        expect($parser->getTitle())->toBe('Parada 2: Riesgos de la ruta | Itinerario para la empleabilidad I');
        expect($parser->getDescription())->toContain('En este REA');
        expect($parser->getAuthor())->toBe('María Cruz García Sanchís y Daniela Gimeno Ruiz para Cedec');
        expect($parser->getLicense())->toBe('creative commons: attribution - share alike 4.0');
        expect($parser->getLearningResourceType())->toBe('real project');
        expect($parser->getLanguage())->toBe('es');


        // Check extracted strings
        $strings = $parser->getStrings();
        expect($strings)->toBeArray();
        // expect(count($strings))->toBeGreaterThan(0);
    
        // Optionally, check for some expected content
        // expect($strings)->toContain('Some expected text from version 3 file');
    }
);

it(
    'can export JSON data from an ELP file',
    function () {
        $elpFile = __DIR__ . '/../Fixtures/04_La_Ilustracion.elp';

        expect(file_exists($elpFile))->toBeTrue('Example ELP file not found');

        $parser = ELPParser::fromFile($elpFile);

        $json = $parser->exportJson();
        $data = json_decode($json, true);

        expect($data)->toBeArray();
        expect($data['title'])->toBe('La Ilustración');

        $expected = json_decode(
            file_get_contents(__DIR__ . '/../Fixtures/04_La_Ilustracion.expected.json'),
            true
        );
        expect($data)->toEqual($expected);

        $temp = tempnam(sys_get_temp_dir(), 'elp') . '.json';
        $parser->exportJson($temp);
        expect(file_exists($temp))->toBeTrue();
        $fileData = json_decode(file_get_contents($temp), true);
        expect($fileData)->toEqual($expected);
        unlink($temp);
    }
);

it(
    'can retrieve full metadata information',
    function () {
        $elpFile = __DIR__ . '/../Fixtures/04_La_Ilustracion.elp';

        expect(file_exists($elpFile))->toBeTrue('Example ELP file not found');

        $parser = ELPParser::fromFile($elpFile);

        $meta = $parser->getMetadata();
        $expected = json_decode(
            file_get_contents(__DIR__ . '/../Fixtures/04_La_Ilustracion.metadata.expected.json'),
            true
        );

        expect($meta)->toEqual($expected);
    }
);

it(
    'can extract an ELP file using a temporary directory', function () {
        $elpFile = __DIR__ . '/../Fixtures/exe2-ipe1_parada3.elp';
    
        // Create a unique temporary directory
        $tempDir = sys_get_temp_dir() . '/elp_extracted_' . uniqid();
        mkdir($tempDir, 0700, true);
    
        try {
            // Create an instance of the parser
            $parser = ELPParser::fromFile($elpFile);
        
            // Attempt to extract to the temporary directory
            $parser->extract($tempDir);
        
            // Verify that the extraction directory was created
            expect(is_dir($tempDir))->toBeTrue('The extraction directory was not created');
        
            // Verify that the contentv3.xml file exists within the extracted files
            expect(file_exists($tempDir . '/contentv3.xml'))->toBeTrue('contentv3.xml not found in the extracted files');
        } finally {
            // Clean up the extracted files
            if (is_dir($tempDir)) {
                array_map('unlink', glob("$tempDir/*"));
                rmdir($tempDir);
            }
        }
    }
);

it(
    'can parse a version v26 simple ELP file', function () {
        $elpFile = __DIR__ . '/../Fixtures/exe26-editado-con-2.6-simplificado.elp';
    
        // Ensure the test file exists
        expect(file_exists($elpFile))->toBeTrue('Test ELP file for version 3 not found');
    
        $parser = ELPParser::fromFile($elpFile);
    
        // Check version detection
        expect($parser->getVersion())->toBe(2);

        // Check metadata fields
        expect($parser->getTitle())->toBe('Accessibility revision');
        expect($parser->getDescription())->toContain('vfggg');
        expect($parser->getAuthor())->toBe('The eXeLearning Team');
        expect($parser->getLicense())->toBe('None');
        expect($parser->getLearningResourceType())->toBe('');
        expect($parser->getLanguage())->toBe('en');


        // Check extracted strings
        $strings = $parser->getStrings();
        expect($strings)->toBeArray();
        // expect(count($strings))->toBeGreaterThan(0);
    
        // Optionally, check for some expected content
        // expect($strings)->toContain('Some expected text from version 3 file');
    }
);

it(
    'can parse a version v26 more simple ELP file', function () {
        $elpFile = __DIR__ . '/../Fixtures/exe26-editado-con-2.6-sencillo.elp';
    
        // Ensure the test file exists
        expect(file_exists($elpFile))->toBeTrue('Test ELP file for version 3 not found');
    
        $parser = ELPParser::fromFile($elpFile);
    
        // Check version detection
        expect($parser->getVersion())->toBe(2);

        // Check metadata fields
        expect($parser->getTitle())->toBe('Contenido para pruebas de eXe 3');
        expect($parser->getDescription())->toContain('Contenido para pruebas de eXe 3. Curso de Diseño Web. Desarrollo web con estándares. Introducción y HTML.');
        expect($parser->getAuthor())->toBe('El Equipo de eXeLearning');
        expect($parser->getLicense())->toBe('None');
        expect($parser->getLearningResourceType())->toBe('master class');
        expect($parser->getLanguage())->toBe('es');


        // Check extracted strings
        $strings = $parser->getStrings();
        expect($strings)->toBeArray();
        // expect(count($strings))->toBeGreaterThan(0);
    
        // Optionally, check for some expected content
        // expect($strings)->toContain('Some expected text from version 3 file');
    }
);

it(
    'throws an exception for invalid ELP file', function () {

        // Test with inexistent file
        $invalidFile0 = __DIR__ . '/../Fixtures/nonexisting.zip';    
        expect(fn() => ELPParser::fromFile($invalidFile0))
            ->toThrow(Exception::class, 'File does not exist.');

        // Test with invalid file
        $invalidFile1 = __DIR__ . '/../Fixtures/invalid.jpg';
        expect(fn() => ELPParser::fromFile($invalidFile1))
            ->toThrow(Exception::class, 'The file is not a valid ZIP file.');

        // Test with ZIP but no XML
        $invalidFile2 = __DIR__ . '/../Fixtures/invalid.zip';    
        expect(fn() => ELPParser::fromFile($invalidFile2))
            ->toThrow(Exception::class, 'Invalid ELP file: No content XML found.');
    


    }
);


