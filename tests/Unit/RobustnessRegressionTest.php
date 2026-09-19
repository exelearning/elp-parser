<?php

/**
 * Regression corpus for malformed and edge-case project inputs.
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
use Exelearning\Exception\InvalidXmlException;
use Exelearning\Parser\IdeviceStateParser;
use RuntimeException;
use ZipArchive;

/**
 * Create a temporary ZIP-backed project with custom content.xml.
 *
 * @param string                $xml     Project XML.
 * @param array<string, string> $entries Additional archive entries.
 *
 * @return string
 */
function createRegressionArchive(string $xml, array $entries = []): string
{
    $temporaryFile = tempnam(sys_get_temp_dir(), 'elp-regression-');
    if ($temporaryFile === false) {
        throw new RuntimeException('Unable to create temporary file.');
    }

    @unlink($temporaryFile);
    $archivePath = $temporaryFile . '.elpx';

    $zip = new ZipArchive();
    if ($zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Unable to create temporary archive.');
    }

    $zip->addFromString('content.xml', $xml);

    foreach ($entries as $path => $contents) {
        $zip->addFromString($path, $contents);
    }

    $zip->close();

    return $archivePath;
}

it(
    'rejects a deterministic corpus of malformed xml documents',
    function () {
        $corpus = [
            '<ode>',
            '<ode><unclosed></ode>',
            '<?xml version="1.0"?><ode><a></b></ode>',
            '<?xml version="1.0"?><ode>&unknown;</ode>',
        ];

        foreach ($corpus as $xml) {
            $archive = createRegressionArchive($xml);

            try {
                expect(fn() => ELPParser::fromFile($archive))
                    ->toThrow(InvalidXmlException::class);
            } finally {
                @unlink($archive);
            }
        }
    }
);

it(
    'handles unicode query fragments url encoding and traversal-like asset references',
    function () {
        $extractor = new AssetReferenceExtractor(
            [
                'content/resources/Imágen ñ.jpg',
                'content/resources/manual.pdf',
            ]
        );

        $pages = [
            [
                'id' => 'PAGE',
                'title' => 'Page',
                'idevices' => [
                    [
                        'id' => 'IDEVICE',
                        'type' => 'text',
                        'html' => '<img src="{{context_path}}/Im%C3%A1gen%20%C3%B1.jpg?size=large#preview">'
                            . '<a href="{{context_path}}/manual.pdf#page=4">Manual</a>'
                            . '<a href="https://example.com/external.pdf">External</a>'
                            . '<a href="{{context_path}}/../../secret.pdf">Traversal</a>',
                        'jsonProperties' => [],
                        'data' => [],
                    ],
                ],
            ],
        ];

        $resolved = array_column($extractor->extract($pages), 'path');
        $broken = array_column($extractor->findBrokenReferences($pages), 'reference');

        expect($resolved)->toContain('content/resources/Imágen ñ.jpg');
        expect($resolved)->toContain('content/resources/manual.pdf');
        expect($broken)->toContain('../../secret.pdf');
        expect($broken)->not->toContain('https://example.com/external.pdf');
    }
);

it(
    'keeps malformed idevice state isolated for every structured storage pattern',
    function () {
        $parser = new IdeviceStateParser();

        $cases = [
            [
                '<div class="flipcards-DataGame js-hidden">%7Bbroken</div>',
                '',
                IdeviceStateParser::PATTERN_DATA_GAME,
            ],
            [
                '<script id="exe-interactive-video-contents" type="application/json">{broken</script>',
                '',
                IdeviceStateParser::PATTERN_EMBEDDED_JSON,
            ],
            [
                '<p>Standard</p>',
                '{broken',
                IdeviceStateParser::PATTERN_STANDARD_JSON,
            ],
        ];

        foreach ($cases as [$html, $json, $pattern]) {
            $state = $parser->parse($html, $json);

            expect($state['storagePattern'])->toBe($pattern);
            expect($state['data'])->toBe([]);
            expect($state['decodeError'])->toBeString();
        }
    }
);

it(
    'reports cyclic page hierarchies without hanging',
    function () {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<ode xmlns="http://www.intef.es/xsd/ode" version="2.0">
  <odeResources>
    <odeResource><key>exe_version</key><value>4.0.0</value></odeResource>
  </odeResources>
  <odeProperties></odeProperties>
  <odeNavStructures>
    <odeNavStructure>
      <odePageId>PAGE-A</odePageId>
      <odeParentPageId>PAGE-B</odeParentPageId>
      <odeNavStructureOrder>1</odeNavStructureOrder>
      <pageName>A</pageName>
      <odeNavStructureProperties></odeNavStructureProperties>
      <odePagStructures></odePagStructures>
    </odeNavStructure>
    <odeNavStructure>
      <odePageId>PAGE-B</odePageId>
      <odeParentPageId>PAGE-A</odeParentPageId>
      <odeNavStructureOrder>1</odeNavStructureOrder>
      <pageName>B</pageName>
      <odeNavStructureProperties></odeNavStructureProperties>
      <odePagStructures></odePagStructures>
    </odeNavStructure>
  </odeNavStructures>
</ode>
XML;

        $archive = createRegressionArchive($xml);

        try {
            $parser = ELPParser::fromFile($archive);
            $result = $parser->validate();

            expect(array_column($result['errors'], 'code'))
                ->toContain('page_hierarchy_cycle');
            expect($parser->getPageTree())->toBe([]);
        } finally {
            @unlink($archive);
        }
    }
);
