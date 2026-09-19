<?php

/**
 * Tests for the complete ODE inspection API.
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
 * Create a modern ODE fixture with preferences, hierarchy and iDevices.
 *
 * @return string
 */
function createCompleteOdeFixture(): string
{
    $temporaryFile = tempnam(sys_get_temp_dir(), 'ode-api-');
    if ($temporaryFile === false) {
        throw new RuntimeException('Unable to create temporary file.');
    }

    @unlink($temporaryFile);
    $archivePath = $temporaryFile . '.elpx';

    $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<ode xmlns="http://www.intef.es/xsd/ode" version="2.0">
  <userPreferences>
    <userPreference><key>theme</key><value>base</value></userPreference>
    <userPreference><key>locale</key><value>es</value></userPreference>
  </userPreferences>
  <odeResources>
    <odeResource><key>odeId</key><value>20260919190000ABCDEF</value></odeResource>
    <odeResource><key>odeVersionId</key><value>20260919190100FEDCBA</value></odeResource>
    <odeResource><key>exe_version</key><value>4.0.0</value></odeResource>
  </odeResources>
  <odeProperties>
    <odeProperty><key>pp_title</key><value>ODE API fixture</value></odeProperty>
    <odeProperty><key>pp_lang</key><value>es</value></odeProperty>
  </odeProperties>
  <odeNavStructures>
    <odeNavStructure>
      <odePageId>PAGE-ROOT</odePageId>
      <odeParentPageId></odeParentPageId>
      <odeNavStructureOrder>1</odeNavStructureOrder>
      <pageName>Root</pageName>
      <odeNavStructureProperties>
        <odeNavStructureProperty><key>titlePage</key><value>Root page</value></odeNavStructureProperty>
      </odeNavStructureProperties>
      <odePagStructures>
        <odePagStructure>
          <odePageId>PAGE-ROOT</odePageId>
          <odeBlockId>BLOCK-ROOT</odeBlockId>
          <odePagStructureOrder>1</odePagStructureOrder>
          <blockName>Root block</blockName>
          <odeComponents>
            <odeComponent>
              <odePageId>PAGE-ROOT</odePageId>
              <odeBlockId>BLOCK-ROOT</odeBlockId>
              <odeIdeviceId>IDEVICE-ROOT</odeIdeviceId>
              <odeIdeviceTypeName>text</odeIdeviceTypeName>
              <odeComponentsOrder>1</odeComponentsOrder>
              <htmlView><![CDATA[<p>Root text</p>]]></htmlView>
              <jsonProperties><![CDATA[{"text":"Root text"}]]></jsonProperties>
              <odeComponentsProperties></odeComponentsProperties>
            </odeComponent>
          </odeComponents>
          <odePagStructureProperties></odePagStructureProperties>
        </odePagStructure>
      </odePagStructures>
    </odeNavStructure>
    <odeNavStructure>
      <odePageId>PAGE-CHILD</odePageId>
      <odeParentPageId>PAGE-ROOT</odeParentPageId>
      <odeNavStructureOrder>2</odeNavStructureOrder>
      <pageName>Child</pageName>
      <odeNavStructureProperties>
        <odeNavStructureProperty><key>titlePage</key><value>Child page</value></odeNavStructureProperty>
      </odeNavStructureProperties>
      <odePagStructures></odePagStructures>
    </odeNavStructure>
  </odeNavStructures>
</ode>
XML;

    $zip = new ZipArchive();
    if ($zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Unable to create temporary ELPX archive.');
    }

    $zip->addFromString('content.xml', $xml);
    $zip->addFromString('content.dtd', '<!ELEMENT ode ANY>');
    $zip->close();

    return $archivePath;
}

it(
    'exposes user preferences resources properties and stable project identifiers',
    function () {
        $archive = createCompleteOdeFixture();

        try {
            $parser = ELPParser::fromFile($archive);

            expect($parser->getUserPreferences())->toBe([
                'theme' => 'base',
                'locale' => 'es',
            ]);
            expect($parser->getProjectId())->toBe('20260919190000ABCDEF');
            expect($parser->getProjectVersionId())->toBe('20260919190100FEDCBA');
            expect($parser->getOdeResources()['exe_version'])->toBe('4.0.0');
            expect($parser->getOdeProperties()['pp_title'])->toBe('ODE API fixture');

            $metadata = $parser->getMetadata();
            expect($metadata['metadata'][0]['content']['user_preferences']['theme'])->toBe('base');
        } finally {
            @unlink($archive);
        }
    }
);

it(
    'supports page block and idevice lookup by identifier',
    function () {
        $archive = createCompleteOdeFixture();

        try {
            $parser = ELPParser::fromFile($archive);

            expect($parser->getPageById('PAGE-ROOT')['title'])->toBe('Root page');
            expect($parser->getPageById('MISSING'))->toBeNull();
            expect($parser->getBlockById('BLOCK-ROOT')['name'])->toBe('Root block');
            expect($parser->getBlockById('MISSING'))->toBeNull();
            expect($parser->getIdeviceById('IDEVICE-ROOT')['type'])->toBe('text');
            expect($parser->getIdeviceById('MISSING'))->toBeNull();
        } finally {
            @unlink($archive);
        }
    }
);

it(
    'builds a nested page tree while retaining the flat page list',
    function () {
        $archive = createCompleteOdeFixture();

        try {
            $parser = ELPParser::fromFile($archive);

            expect($parser->getPages())->toHaveCount(2);

            $tree = $parser->getPageTree();
            expect($tree)->toHaveCount(1);
            expect($tree[0]['id'])->toBe('PAGE-ROOT');
            expect($tree[0]['children'])->toHaveCount(1);
            expect($tree[0]['children'][0]['id'])->toBe('PAGE-CHILD');
        } finally {
            @unlink($archive);
        }
    }
);

it(
    'exports a detailed project representation without changing compact json serialization',
    function () {
        $archive = createCompleteOdeFixture();

        try {
            $parser = ELPParser::fromFile($archive);

            $compact = json_decode($parser->exportJson(), true);
            $detailed = json_decode($parser->exportDetailedJson(), true);

            expect($compact)->toHaveKey('title');
            expect($compact)->not->toHaveKey('pages');
            expect($detailed['summary']['title'])->toBe('ODE API fixture');
            expect($detailed['format']['version'])->toBe('2.0');
            expect($detailed['pages'])->toHaveCount(2);
            expect($detailed['pageTree'][0]['children'])->toHaveCount(1);
            expect($detailed['userPreferences']['theme'])->toBe('base');
            expect($detailed['odeResources']['odeId'])->toBe('20260919190000ABCDEF');
        } finally {
            @unlink($archive);
        }
    }
);
