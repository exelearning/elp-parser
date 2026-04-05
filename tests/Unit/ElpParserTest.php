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
    'can parse a version 3 ELP file',
    function () {
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
    'can parse another version 3 ELP file',
    function () {
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
    'can parse a version 2 ELP file',
    function () {
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
    'can extract an ELP file using a temporary directory',
    function () {
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
    'can parse a version v26 simple ELP file',
    function () {
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
    'can parse a version v26 more simple ELP file',
    function () {
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
    'throws an exception for invalid ELP file',
    function () {

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

it(
    'can parse a modern ELPX file and expose extended format metadata',
    function () {
        $elpxFile = __DIR__ . '/../Fixtures/un-contenido-de-ejemplo-para-probar-estilos-y-catalogacion.elpx';

        expect(file_exists($elpxFile))->toBeTrue('Test ELPX file not found');

        $parser = ELPParser::fromFile($elpxFile);

        expect($parser->getVersion())->toBe(4);
        expect($parser->getSourceExtension())->toBe('elpx');
        expect($parser->getContentFormat())->toBe('ode-content');
        expect($parser->getContentFile())->toBe('content.xml');
        expect($parser->getContentSchemaVersion())->toBe('2.0');
        expect($parser->getExeVersion())->toBe('3.0');
        expect($parser->isLegacyFormat())->toBeFalse();
        expect($parser->hasRootDtd())->toBeTrue();
        expect($parser->getResourceLayout())->toBe('content-resources');
        expect($parser->isLikelyVersion4Package())->toBeTrue();

        expect($parser->getTitle())->toBe('Un contenido de ejemplo para probar estilos y catalogación');
        expect($parser->getAuthor())->toBe('Ignacio Gros');
        expect($parser->getDescription())->toContain('Descripción general');
        expect($parser->getLanguage())->toBe('es');

        $pages = $parser->getPages();
        expect($pages)->toBeArray();
        expect(count($pages))->toBe(14);
        expect($pages[0]['title'])->toBe('Inicio');
        expect($pages[0]['idevices'])->toBeArray();
        expect($pages[0]['idevices'][0]['type'])->toBe('text');

        $assets = $parser->getAssets();
        expect($assets)->toContain('content/resources/00.jpg');
        expect($assets)->toContain('content/resources/colegio.mp3');

        $metadata = $parser->getMetadata();
        expect($metadata['metadata'][0]['content']['format']['container'])->toBe('elpx');
        expect($metadata['metadata'][0]['content']['project_resources']['exe_version'])->toBe('3.0');
        expect($metadata['metadata'][0]['content']['format']['likely_version_4'])->toBeTrue();
    }
);

it(
    'can parse elpx page and component visibility properties',
    function () {
        $elpxFile = __DIR__ . '/../Fixtures/propiedades.elpx';

        expect(file_exists($elpxFile))->toBeTrue('Properties ELPX fixture not found');

        $parser = ELPParser::fromFile($elpxFile);
        $pages = $parser->getPages();

        expect($parser->getVersion())->toBe(4);
        expect($parser->getTitle())->toBe('propiedades');
        expect($parser->getLanguage())->toBe('eu');
        expect($parser->getLicense())->toBe('creative commons: attribution - share alike 4.0');
        expect($parser->hasRootDtd())->toBeTrue();
        expect($parser->getResourceLayout())->toBe('none');
        expect($parser->isLikelyVersion4Package())->toBeTrue();
        expect(count($pages))->toBe(6);
        expect($pages[0]['title'])->toBe('Propiedades idevices');
        expect($pages[0]['idevices'][0]['visible'])->toBeTrue();
        expect($pages[0]['idevices'][1]['visible'])->toBeFalse();
        expect($pages[0]['idevices'][2]['teacherOnly'])->toBeTrue();
        expect($pages[3]['pageName'])->toBe('Propiedades páginas - otro título');
        expect($pages[3]['title'])->toBe('otro título!!!!!!!!!!!!!!');
        expect($pages[3]['editableInPage'])->toBeTrue();
        expect($pages[4]['visible'])->toBeFalse();
        expect($parser->getStrings())->toContain('no visible en exportación');
    }
);

it(
    'distinguishes modern elp packages from likely version 4 elpx packages',
    function () {
        $modernElp = ELPParser::fromFile(__DIR__ . '/../Fixtures/exe3-accessibility-revision.elp');
        $modernElpx = ELPParser::fromFile(__DIR__ . '/../Fixtures/un-contenido-de-ejemplo-para-probar-estilos-y-catalogacion.elpx');

        expect($modernElp->getContentFormat())->toBe('ode-content');
        expect($modernElp->getSourceExtension())->toBe('elp');
        expect($modernElp->hasRootDtd())->toBeFalse();
        expect($modernElp->isLikelyVersion4Package())->toBeFalse();
        expect($modernElp->getResourceLayout())->toBe('content-resources');
        expect($modernElp->getVersion())->toBe(3);

        expect($modernElpx->getContentFormat())->toBe('ode-content');
        expect($modernElpx->getSourceExtension())->toBe('elpx');
        expect($modernElpx->hasRootDtd())->toBeTrue();
        expect($modernElpx->isLikelyVersion4Package())->toBeTrue();
        expect($modernElpx->getResourceLayout())->toBe('content-resources');
        expect($modernElpx->getVersion())->toBe(4);
    }
);

it(
    'lists assets by type and exposes detailed asset origins',
    function () {
        $parser = ELPParser::fromFile(
            __DIR__ . '/../Fixtures/un-contenido-de-ejemplo-para-probar-estilos-y-catalogacion.elpx'
        );

        $allAssets = $parser->getAssets();
        $images = $parser->getImages();
        $audio = $parser->getAudioFiles();
        $video = $parser->getVideoFiles();
        $documents = $parser->getDocuments();
        $detailed = $parser->getAssetsDetailed();

        expect($allAssets)->toContain('content/resources/00.jpg');
        expect($allAssets)->toContain('content/resources/colegio.mp3');

        expect($images)->toContain('content/resources/00.jpg');
        expect($images)->toContain('content/resources/01.jpg');
        expect($audio)->toBe(['content/resources/colegio.mp3']);
        expect($video)->toBeArray()->toHaveCount(0);
        expect($documents)->toBeArray()->toHaveCount(0);

        expect($detailed)->toBeArray();
        expect(count($detailed))->toBeGreaterThan(0);

        $imageAsset = null;
        $audioAsset = null;
        foreach ($detailed as $asset) {
            if ($asset['path'] === 'content/resources/00.jpg') {
                $imageAsset = $asset;
            }
            if ($asset['path'] === 'content/resources/colegio.mp3') {
                $audioAsset = $asset;
            }
        }

        expect($imageAsset)->toBeArray();
        expect($imageAsset['type'])->toBe('image');
        expect($imageAsset['extension'])->toBe('jpg');
        expect($imageAsset['occurrences'])->toBeGreaterThan(0);
        expect($imageAsset['pages'])->toBeArray();
        expect($imageAsset['idevices'])->toBeArray();
        expect($imageAsset['pages'][0]['title'])->toBe('Inicio');

        expect($audioAsset)->toBeArray();
        expect($audioAsset['type'])->toBe('audio');
        expect($audioAsset['extension'])->toBe('mp3');
    }
);

it(
    'lists visible pages blocks idevices and grouped page texts',
    function () {
        $parser = ELPParser::fromFile(__DIR__ . '/../Fixtures/propiedades.elpx');

        $pages = $parser->getPages();
        $visiblePages = $parser->getVisiblePages();
        $blocks = $parser->getBlocks();
        $idevices = $parser->getIdevices();
        $pageTexts = $parser->getPageTexts();

        expect($pages)->toHaveCount(6);
        expect($visiblePages)->toHaveCount(5);
        expect($blocks)->toBeArray();
        expect(count($blocks))->toBeGreaterThan(0);
        expect($idevices)->toBeArray();
        expect(count($idevices))->toBeGreaterThan(0);
        expect($pageTexts)->toHaveCount(6);

        expect($blocks[0]['pageTitle'])->toBe('Propiedades idevices');
        expect($idevices[0]['pageTitle'])->toBe('Propiedades idevices');

        $hiddenPage = null;
        $firstPageText = null;

        foreach ($pageTexts as $pageText) {
            if ($pageText['title'] === 'Propiedades idevices') {
                $firstPageText = $pageText;
            }
            if ($pageText['pageName'] === 'Propiedades páginas - no visible') {
                $hiddenPage = $pageText;
            }
        }

        expect($firstPageText)->toBeArray();
        expect($firstPageText['texts'])->toContain('normal');
        expect($firstPageText['texts'])->toContain('no visible en exportación');
        expect($firstPageText['text'])->toContain('visible solo en modo docente');

        expect($hiddenPage)->toBeArray();
        expect($hiddenPage['visible'])->toBeFalse();

        $pageById = $parser->getPageTextById($pages[0]['id']);
        expect($pageById)->toBeArray();
        expect($pageById['title'])->toBe('Propiedades idevices');
        expect($parser->getPageTextById('missing-page-id'))->toBeNull();
    }
);

it(
    'lists visible page texts teacher only idevices hidden idevices and orphan assets',
    function () {
        $propertiesParser = ELPParser::fromFile(__DIR__ . '/../Fixtures/propiedades.elpx');

        $visiblePageTexts = $propertiesParser->getVisiblePageTexts();
        $teacherOnlyIdevices = $propertiesParser->getTeacherOnlyIdevices();
        $hiddenIdevices = $propertiesParser->getHiddenIdevices();
        $orphans = $propertiesParser->getOrphanAssets();

        expect($visiblePageTexts)->toHaveCount(5);
        foreach ($visiblePageTexts as $pageText) {
            expect($pageText['visible'])->toBeTrue();
        }

        expect($teacherOnlyIdevices)->toHaveCount(1);
        expect($teacherOnlyIdevices[0]['teacherOnly'])->toBeTrue();
        expect($teacherOnlyIdevices[0]['pageTitle'])->toBe('Propiedades idevices');

        expect($hiddenIdevices)->toHaveCount(1);
        expect($hiddenIdevices[0]['visible'])->toBeFalse();
        expect($hiddenIdevices[0]['pageTitle'])->toBe('Propiedades idevices');

        expect($orphans)->toContain('content/img/exe_powered_logo.png');
        expect($orphans)->toContain('theme/screenshot.png');

        $contentParser = ELPParser::fromFile(
            __DIR__ . '/../Fixtures/un-contenido-de-ejemplo-para-probar-estilos-y-catalogacion.elpx'
        );
        $contentOrphans = $contentParser->getOrphanAssets();

        expect($contentOrphans)->toContain('theme/screenshot.png');
        expect($contentOrphans)->not->toContain('content/resources/00.jpg');
        expect($contentOrphans)->not->toContain('content/resources/colegio.mp3');
    }
);
