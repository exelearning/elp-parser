<?php

/**
 * Compatibility tests for eXeLearning 3 and 4 ELPX packages.
 *
 * @category Tests
 * @package  ELPParser
 * @author   INTEF <cedec@educacion.gob.es>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/exelearning/elp-parser
 */

namespace Exelearning\ElpParser\Tests\Unit;

use Exelearning\Asset\AssetReferenceExtractor;
use Exelearning\ELPParser;
use RuntimeException;
use ZipArchive;

/**
 * Create a minimal modern ELPX archive for compatibility tests.
 *
 * @param array<string, string> $resources ODE resource key/value pairs.
 * @param array<string, string> $entries   Additional archive entries.
 *
 * @return string
 */
function createElpxCompatibilityFixture(array $resources, array $entries = []): string
{
    $temporaryFile = tempnam(sys_get_temp_dir(), 'elpx-compat-');
    if ($temporaryFile === false) {
        throw new RuntimeException('Unable to create temporary file.');
    }

    @unlink($temporaryFile);
    $archivePath = $temporaryFile . '.elpx';
    $resourceXml = '';

    foreach ($resources as $key => $value) {
        $resourceXml .= '<odeResource><key>'
            . htmlspecialchars($key, ENT_QUOTES | ENT_XML1, 'UTF-8')
            . '</key><value>'
            . htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8')
            . '</value></odeResource>';
    }

    $contentXml = '<?xml version="1.0" encoding="UTF-8"?>'
        . '<ode xmlns="http://www.intef.es/xsd/ode" version="2.0">'
        . '<odeResources>' . $resourceXml . '</odeResources>'
        . '<odeProperties>'
        . '<odeProperty><key>pp_title</key><value>Compatibility fixture</value></odeProperty>'
        . '</odeProperties>'
        . '<odeNavStructures></odeNavStructures>'
        . '</ode>';

    $zip = new ZipArchive();
    if ($zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Unable to create temporary ELPX archive.');
    }

    $zip->addFromString('content.xml', $contentXml);

    foreach ($entries as $path => $contents) {
        $zip->addFromString($path, $contents);
    }

    $zip->close();

    return $archivePath;
}

it(
    'parses v3 elpx metadata while keeping the ODE 2.0 format version',
    function () {
        $archive = createElpxCompatibilityFixture(
            ['eXeVersion' => 'v3.0.4'],
            ['content/resources/20250101123456ABCDEF/image.jpg' => 'image']
        );

        try {
            $parser = ELPParser::fromFile($archive);

            expect($parser->getVersion())->toBe(3);
            expect($parser->getExeVersion())->toBe('v3.0.4');
            expect($parser->getApplicationVersion())->toBe('v3.0.4');
            expect($parser->getFormatFamily())->toBe('ode');
            expect($parser->getFormatVersion())->toBe('2.0');
            expect($parser->getPackageProfile())->toBe('elpx-v3');
            expect($parser->getResourceLayout())->toBe('content-resources');
            expect($parser->getResourceProfile())->toBe('v3-uuid-resources');
            expect($parser->getVersionInfo()['source'])->toBe('metadata');
        } finally {
            @unlink($archive);
        }
    }
);

it(
    'identifies v4 package conventions without treating ODE 2.0 as format version 4',
    function () {
        $archive = createElpxCompatibilityFixture(
            ['exe_version' => '3.0'],
            [
                'content.dtd' => '<!ELEMENT ode ANY>',
                'index.html' => '<!doctype html>',
                'screenshot.png' => 'png',
                'content/resources/photos/image.jpg' => 'image',
            ]
        );

        try {
            $parser = ELPParser::fromFile($archive);

            expect($parser->getVersion())->toBe(4);
            expect($parser->getApplicationVersion())->toBe('3.0');
            expect($parser->getFormatFamily())->toBe('ode');
            expect($parser->getFormatVersion())->toBe('2.0');
            expect($parser->getPackageProfile())->toBe('elpx-v4');
            expect($parser->getResourceProfile())->toBe('v4-resource-tree');
            expect($parser->isLikelyVersion4Package())->toBeTrue();
            expect($parser->getVersionInfo()['source'])->toBe('heuristic');
        } finally {
            @unlink($archive);
        }
    }
);

it(
    'prefers modern exe_version over the legacy eXeVersion key',
    function () {
        $archive = createElpxCompatibilityFixture(
            [
                'eXeVersion' => 'v3.0.0',
                'exe_version' => '4.1.0',
            ]
        );

        try {
            $parser = ELPParser::fromFile($archive);

            expect($parser->getApplicationVersion())->toBe('4.1.0');
            expect($parser->getVersion())->toBe(4);
            expect($parser->getPackageProfile())->toBe('elpx-v4');
            expect($parser->getVersionInfo()['source'])->toBe('metadata');
        } finally {
            @unlink($archive);
        }
    }
);

it(
    'resolves both v3 long-form and v4 context-path asset references',
    function () {
        $extractor = new AssetReferenceExtractor(
            [
                'content/resources/photo.jpg',
                'content/resources/lessons/document.pdf',
                'content/resources/20250101123456ABCDEF/legacy.png',
            ]
        );

        $assets = $extractor->extract(
            [
                [
                    'id' => 'page-1',
                    'title' => 'Page',
                    'idevices' => [
                        [
                            'id' => 'idevice-1',
                            'type' => 'text',
                            'html' => '<img src="{{context_path}}/photo.jpg">'
                                . '<a href="{{context_path}}/lessons/document.pdf">Document</a>'
                                . '<img src="{{context_path}}/content/resources/'
                                . '20250101123456ABCDEF/legacy.png">',
                            'jsonProperties' => [],
                        ],
                    ],
                ],
            ]
        );

        $paths = array_column($assets, 'path');

        expect($paths)->toContain('content/resources/photo.jpg');
        expect($paths)->toContain('content/resources/lessons/document.pdf');
        expect($paths)->toContain('content/resources/20250101123456ABCDEF/legacy.png');
    }
);

it(
    'detects packages containing both v3 UUID resources and v4 resource-tree assets',
    function () {
        $archive = createElpxCompatibilityFixture(
            ['exe_version' => '4.0.0'],
            [
                'content/resources/20250101123456ABCDEF/legacy.png' => 'legacy',
                'content/resources/folder/current.png' => 'current',
            ]
        );

        try {
            $parser = ELPParser::fromFile($archive);

            expect($parser->getResourceLayout())->toBe('content-resources');
            expect($parser->getResourceProfile())->toBe('mixed-modern-resources');
            expect($parser->getPackageProfile())->toBe('elpx-v4');
        } finally {
            @unlink($archive);
        }
    }
);
