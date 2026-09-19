<?php

/**
 * Tests for parser hardening and internal helpers.
 *
 * @category Tests
 * @package  ELPParser
 * @author   INTEF <cedec@educacion.gob.es>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/exelearning/elp-parser
 */

namespace Exelearning\ElpParser\Tests\Unit;

use Exelearning\Archive\ArchiveLimits;
use Exelearning\Asset\AssetReferenceExtractor;
use Exelearning\ELPParser;
use Exelearning\Exception\ResourceLimitException;
use Exelearning\Exception\UnsafeArchiveException;
use Exelearning\Support\Slugger;
use Exelearning\Support\VersionDetector;
use ZipArchive;

it(
    'autoloads the public parser through composer psr-4 rules',
    function () {
        expect(class_exists(ELPParser::class))->toBeTrue();
    }
);

it(
    'detects multi-digit major versions without conflating metadata and heuristics',
    function () {
        $detector = new VersionDetector();

        $metadata = $detector->detectModern('10.2.1', false);
        expect($metadata['declared'])->toBe('10.2.1');
        expect($metadata['declaredMajor'])->toBe(10);
        expect($metadata['detectedMajor'])->toBe(10);
        expect($metadata['source'])->toBe('metadata');

        $heuristic = $detector->detectModern('3.0', true);
        expect($heuristic['declared'])->toBe('3.0');
        expect($heuristic['declaredMajor'])->toBe(3);
        expect($heuristic['detectedMajor'])->toBe(4);
        expect($heuristic['source'])->toBe('heuristic');
    }
);

it(
    'extracts archive-backed assets from html css srcset and json properties',
    function () {
        $extractor = new AssetReferenceExtractor(
            [
                'content/resources/image one.jpg',
                'content/resources/second.png',
                'content/resources/audio.mp3',
                'content/resources/poster.webp',
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
                            'html' => '<img src="{{context_path}}/content/resources/image%20one.jpg" '
                                . 'srcset="content/resources/second.png 2x" '
                                . 'style="background:url(content/resources/audio.mp3)">',
                            'jsonProperties' => [
                                'media' => [
                                    'poster' => 'content/resources/poster.webp',
                                ],
                            ],
                        ],
                    ],
                ],
            ]
        );

        $paths = array_column($assets, 'path');

        expect($paths)->toContain('content/resources/image one.jpg');
        expect($paths)->toContain('content/resources/second.png');
        expect($paths)->toContain('content/resources/audio.mp3');
        expect($paths)->toContain('content/resources/poster.webp');
    }
);

it(
    'rejects unsafe archive paths before parsing',
    function () {
        $archive = tempnam(sys_get_temp_dir(), 'elp-unsafe-') . '.elp';
        $zip = new ZipArchive();
        $zip->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('content.xml', '<ode version="2.0"></ode>');
        $zip->addFromString('../outside.txt', 'unsafe');
        $zip->close();

        try {
            expect(fn() => ELPParser::fromFile($archive))->toThrow(UnsafeArchiveException::class);
        } finally {
            @unlink($archive);
        }
    }
);

it(
    'enforces configurable xml size limits',
    function () {
        $archive = tempnam(sys_get_temp_dir(), 'elp-limit-') . '.elp';
        $zip = new ZipArchive();
        $zip->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('content.xml', '<ode version="2.0"><padding>' . str_repeat('x', 256) . '</padding></ode>');
        $zip->close();

        $limits = new ArchiveLimits(maxXmlBytes: 64);

        try {
            expect(fn() => ELPParser::fromFile($archive, $limits))->toThrow(ResourceLimitException::class);
        } finally {
            @unlink($archive);
        }
    }
);

it(
    'creates stable ascii slugs without wordpress helper code',
    function () {
        expect((new Slugger())->slug('La Ilustración Española'))->toBe('la_ilustracion_espanola');
    }
);
