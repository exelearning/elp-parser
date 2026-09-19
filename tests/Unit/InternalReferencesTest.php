<?php

/**
 * Tests for internal links and package manifest inspection.
 *
 * @category Tests
 * @package  ELPParser
 * @author   INTEF <cedec@educacion.gob.es>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/exelearning/elp-parser
 */

namespace Exelearning\ElpParser\Tests\Unit;

use Exelearning\ELPParser;
use RuntimeException;
use ZipArchive;

/**
 * Create a modern fixture with internal links and packaged iDevice runtimes.
 *
 * @return string
 */
function createInternalReferenceFixture(): string
{
    $temporaryFile = tempnam(sys_get_temp_dir(), 'elp-links-');
    if ($temporaryFile === false) {
        throw new RuntimeException('Unable to create temporary file.');
    }

    @unlink($temporaryFile);
    $archivePath = $temporaryFile . '.elpx';

    $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<ode xmlns="http://www.intef.es/xsd/ode" version="2.0">
  <odeResources>
    <odeResource><key>exe_version</key><value>4.0.0</value></odeResource>
  </odeResources>
  <odeProperties>
    <odeProperty><key>pp_title</key><value>Internal links</value></odeProperty>
  </odeProperties>
  <odeNavStructures>
    <odeNavStructure>
      <odePageId>PAGE-A</odePageId>
      <odeParentPageId></odeParentPageId>
      <odeNavStructureOrder>1</odeNavStructureOrder>
      <pageName>Page A</pageName>
      <odeNavStructureProperties></odeNavStructureProperties>
      <odePagStructures>
        <odePagStructure>
          <odePageId>PAGE-A</odePageId>
          <odeBlockId>BLOCK-A</odeBlockId>
          <odePagStructureOrder>1</odePagStructureOrder>
          <blockName>Block A</blockName>
          <odeComponents>
            <odeComponent>
              <odePageId>PAGE-A</odePageId>
              <odeBlockId>BLOCK-A</odeBlockId>
              <odeIdeviceId>TEXT-A</odeIdeviceId>
              <odeIdeviceTypeName>text</odeIdeviceTypeName>
              <odeComponentsOrder>1</odeComponentsOrder>
              <htmlView><![CDATA[
                <p><a href="exe-node:PAGE-B">Valid</a></p>
                <p><a href="exe-node:MISSING-PAGE">Broken</a></p>
              ]]></htmlView>
              <jsonProperties><![CDATA[
                {"secondaryLink":"exe-node:PAGE-B"}
              ]]></jsonProperties>
              <odeComponentsProperties></odeComponentsProperties>
            </odeComponent>
            <odeComponent>
              <odePageId>PAGE-A</odePageId>
              <odeBlockId>BLOCK-A</odeBlockId>
              <odeIdeviceId>CUSTOM-A</odeIdeviceId>
              <odeIdeviceTypeName>custom-runtime</odeIdeviceTypeName>
              <odeComponentsOrder>2</odeComponentsOrder>
              <htmlView><![CDATA[<p>Custom</p>]]></htmlView>
              <jsonProperties><![CDATA[{}]]></jsonProperties>
              <odeComponentsProperties></odeComponentsProperties>
            </odeComponent>
          </odeComponents>
          <odePagStructureProperties></odePagStructureProperties>
        </odePagStructure>
      </odePagStructures>
    </odeNavStructure>
    <odeNavStructure>
      <odePageId>PAGE-B</odePageId>
      <odeParentPageId>PAGE-A</odeParentPageId>
      <odeNavStructureOrder>2</odeNavStructureOrder>
      <pageName>Page B</pageName>
      <odeNavStructureProperties></odeNavStructureProperties>
      <odePagStructures></odePagStructures>
    </odeNavStructure>
  </odeNavStructures>
</ode>
XML;

    $zip = new ZipArchive();
    if ($zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Unable to create temporary ELPX archive.');
    }

    $entries = [
        'content.xml' => $xml,
        'content.dtd' => '<!ELEMENT ode ANY>',
        'index.html' => '<!doctype html>',
        'screenshot.png' => 'png',
        'theme/style.css' => 'body{}',
        'libs/common.js' => 'console.log("common");',
        'idevices/text/config.xml' => '<config/>',
        'idevices/text/export/text.js' => 'console.log("text");',
        'content/resources/image.jpg' => 'image',
        'search_index.js' => '[]',
    ];

    foreach ($entries as $path => $data) {
        $zip->addFromString($path, $data);
    }

    $zip->close();

    return $archivePath;
}

it(
    'extracts valid and broken internal exe-node references',
    function () {
        $archive = createInternalReferenceFixture();

        try {
            $parser = ELPParser::fromFile($archive);
            $links = $parser->getInternalLinks();

            expect($links)->toHaveCount(2);

            $targets = array_column($links, 'targetPageId');
            sort($targets);

            expect($targets)->toBe(['MISSING-PAGE', 'PAGE-B']);

            $valid = null;
            foreach ($links as $link) {
                if ($link['targetPageId'] === 'PAGE-B') {
                    $valid = $link;
                }
            }

            expect($valid)->toBeArray();
            expect($valid['occurrences'])->toBe(2);

            $broken = $parser->getBrokenInternalLinks();
            expect($broken)->toHaveCount(1);
            expect($broken[0]['targetPageId'])->toBe('MISSING-PAGE');
            expect($broken[0]['pageId'])->toBe('PAGE-A');
            expect($broken[0]['ideviceId'])->toBe('TEXT-A');
        } finally {
            @unlink($archive);
        }
    }
);

it(
    'compares used idevice types with packaged runtime directories',
    function () {
        $archive = createInternalReferenceFixture();

        try {
            $parser = ELPParser::fromFile($archive);

            expect($parser->getUsedIdeviceTypes())->toBe([
                'custom-runtime',
                'text',
            ]);
            expect($parser->getAvailableIdeviceTypes())->toBe(['text']);
            expect($parser->getMissingIdeviceRuntimes())->toBe([
                'custom-runtime',
            ]);
        } finally {
            @unlink($archive);
        }
    }
);

it(
    'builds a categorized package manifest',
    function () {
        $archive = createInternalReferenceFixture();

        try {
            $manifest = ELPParser::fromFile($archive)->getPackageManifest();

            expect($manifest['rootFiles'])->toContain('content.xml');
            expect($manifest['rootFiles'])->toContain('index.html');
            expect($manifest['themeFiles'])->toBe(['theme/style.css']);
            expect($manifest['libraryFiles'])->toBe(['libs/common.js']);
            expect($manifest['ideviceFiles']['text'])->toContain('idevices/text/config.xml');
            expect($manifest['resourceFiles'])->toBe(['content/resources/image.jpg']);
            expect($manifest['otherFiles'])->toBe([]);
            expect($manifest['missingIdeviceRuntimes'])->toBe(['custom-runtime']);
        } finally {
            @unlink($archive);
        }
    }
);

it(
    'reports broken internal links and missing runtimes through validation',
    function () {
        $archive = createInternalReferenceFixture();

        try {
            $result = ELPParser::fromFile($archive)->validate();

            expect(array_column($result['errors'], 'code'))
                ->toContain('broken_internal_link');
            expect(array_column($result['warnings'], 'code'))
                ->toContain('missing_idevice_runtime');
        } finally {
            @unlink($archive);
        }
    }
);
