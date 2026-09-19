<?php

/**
 * Tests for package and schema validation.
 *
 * @category Tests
 * @package  ELPParser
 * @author   INTEF <cedec@educacion.gob.es>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/exelearning/elp-parser
 */

namespace Exelearning\ElpParser\Tests\Unit;

use Exelearning\ELPParser;
use Exelearning\Validation\SchemaValidator;
use RuntimeException;
use ZipArchive;

/**
 * Create a deliberately inconsistent modern package.
 *
 * @return string
 */
function createValidationFixture(): string
{
    $temporaryFile = tempnam(sys_get_temp_dir(), 'elp-validation-');
    if ($temporaryFile === false) {
        throw new RuntimeException('Unable to create temporary file.');
    }

    @unlink($temporaryFile);
    $archivePath = $temporaryFile . '.elpx';

    $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<ode xmlns="http://www.intef.es/xsd/ode" version="2.0">
  <odeResources>
    <odeResource><key>odeId</key><value>20260919190000ABCDEF</value></odeResource>
    <odeResource><key>odeVersionId</key><value>20260919190100FEDCBA</value></odeResource>
    <odeResource><key>exe_version</key><value>3.0</value></odeResource>
  </odeResources>
  <odeProperties>
    <odeProperty><key>pp_title</key><value>Validation fixture</value></odeProperty>
  </odeProperties>
  <odeNavStructures>
    <odeNavStructure>
      <odePageId>PAGE-A</odePageId>
      <odeParentPageId>MISSING-PARENT</odeParentPageId>
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
              <odeIdeviceId>IDEVICE-A</odeIdeviceId>
              <odeIdeviceTypeName>text</odeIdeviceTypeName>
              <odeComponentsOrder>1</odeComponentsOrder>
              <htmlView><![CDATA[<img src="{{context_path}}/missing.jpg">]]></htmlView>
              <jsonProperties><![CDATA[{}]]></jsonProperties>
              <odeComponentsProperties></odeComponentsProperties>
            </odeComponent>
          </odeComponents>
          <odePagStructureProperties></odePagStructureProperties>
        </odePagStructure>
      </odePagStructures>
    </odeNavStructure>
  </odeNavStructures>
</ode>
XML;

    $zip = new ZipArchive();
    if ($zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Unable to create temporary archive.');
    }

    $zip->addFromString('content.xml', $xml);
    $zip->addFromString('content.dtd', '<!ELEMENT ode ANY>');
    $zip->close();

    return $archivePath;
}

/**
 * Create a minimal XML-only ODE package for schema tests.
 *
 * @return string
 */
function createSchemaFixture(): string
{
    $temporaryFile = tempnam(sys_get_temp_dir(), 'elp-schema-');
    if ($temporaryFile === false) {
        throw new RuntimeException('Unable to create temporary file.');
    }

    @unlink($temporaryFile);
    $archivePath = $temporaryFile . '.elpx';

    $zip = new ZipArchive();
    if ($zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Unable to create temporary archive.');
    }

    $zip->addFromString(
        'content.xml',
        '<?xml version="1.0" encoding="UTF-8"?>'
        . '<ode xmlns="http://www.intef.es/xsd/ode" version="2.0"></ode>'
    );
    $zip->close();

    return $archivePath;
}

it(
    'reports unresolved asset references instead of silently dropping them',
    function () {
        $archive = createValidationFixture();

        try {
            $parser = ELPParser::fromFile($archive);

            expect($parser->getMissingAssets())->toBe(['missing.jpg']);

            $broken = $parser->getBrokenReferences();
            expect($broken)->toHaveCount(1);
            expect($broken[0]['reference'])->toBe('missing.jpg');
            expect($broken[0]['pageId'])->toBe('PAGE-A');
            expect($broken[0]['ideviceId'])->toBe('IDEVICE-A');
        } finally {
            @unlink($archive);
        }
    }
);

it(
    'validates structural errors while keeping v4 baseline checks as warnings',
    function () {
        $archive = createValidationFixture();

        try {
            $parser = ELPParser::fromFile($archive);
            $result = $parser->validate();

            $errorCodes = array_column($result['errors'], 'code');
            $warningCodes = array_column($result['warnings'], 'code');

            expect($result['valid'])->toBeFalse();
            expect($errorCodes)->toContain('missing_asset');
            expect($errorCodes)->toContain('missing_parent_page');
            expect($warningCodes)->toContain('missing_v4_baseline_file');
            expect($warningCodes)->toContain('missing_v4_baseline_directory');
        } finally {
            @unlink($archive);
        }
    }
);

it(
    'validates project xml against a trusted local xsd',
    function () {
        $archive = createSchemaFixture();
        $xsd = tempnam(sys_get_temp_dir(), 'ode-schema-');

        if ($xsd === false) {
            throw new RuntimeException('Unable to create temporary XSD.');
        }

        file_put_contents(
            $xsd,
            '<?xml version="1.0" encoding="UTF-8"?>'
            . '<xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema" '
            . 'targetNamespace="http://www.intef.es/xsd/ode" '
            . 'xmlns="http://www.intef.es/xsd/ode" elementFormDefault="qualified">'
            . '<xs:element name="ode"><xs:complexType>'
            . '<xs:attribute name="version" type="xs:string" use="required"/>'
            . '</xs:complexType></xs:element>'
            . '</xs:schema>'
        );

        try {
            $result = ELPParser::fromFile($archive)->validateSchema($xsd);

            expect($result['valid'])->toBeTrue();
            expect($result['errors'])->toBe([]);
        } finally {
            @unlink($archive);
            @unlink($xsd);
        }
    }
);

it(
    'validates project xml against a caller supplied trusted dtd',
    function () {
        $archive = createSchemaFixture();
        $dtd = tempnam(sys_get_temp_dir(), 'ode-dtd-');

        if ($dtd === false) {
            throw new RuntimeException('Unable to create temporary DTD.');
        }

        file_put_contents(
            $dtd,
            '<!ELEMENT ode EMPTY>'
            . '<!ATTLIST ode xmlns CDATA #IMPLIED version CDATA #REQUIRED>'
        );

        try {
            $result = ELPParser::fromFile($archive)->validateSchema(
                $dtd,
                SchemaValidator::TYPE_DTD
            );

            expect($result['valid'])->toBeTrue();
            expect($result['errors'])->toBe([]);
        } finally {
            @unlink($archive);
            @unlink($dtd);
        }
    }
);
