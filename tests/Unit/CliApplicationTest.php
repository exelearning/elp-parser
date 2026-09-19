<?php

/**
 * Tests for the dependency-free CLI application.
 *
 * @category Tests
 * @package  ELPParser
 * @author   INTEF <cedec@educacion.gob.es>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/exelearning/elp-parser
 */

namespace Exelearning\ElpParser\Tests\Unit;

use Exelearning\Cli\Application;
use RuntimeException;
use ZipArchive;

/**
 * Create a small modern package for CLI tests.
 *
 * @param string $title        Project title.
 * @param string $assetContent Asset bytes.
 *
 * @return string
 */
function createCliFixture(string $title = 'CLI fixture', string $assetContent = 'image'): string
{
    $temporaryFile = tempnam(sys_get_temp_dir(), 'elp-cli-');
    if ($temporaryFile === false) {
        throw new RuntimeException('Unable to create temporary file.');
    }

    @unlink($temporaryFile);
    $archivePath = $temporaryFile . '.elpx';

    $xml = '<?xml version="1.0" encoding="UTF-8"?>'
        . '<ode xmlns="http://www.intef.es/xsd/ode" version="2.0">'
        . '<odeResources>'
        . '<odeResource><key>exe_version</key><value>4.0.0</value></odeResource>'
        . '</odeResources>'
        . '<odeProperties>'
        . '<odeProperty><key>pp_title</key><value>'
        . htmlspecialchars($title, ENT_QUOTES | ENT_XML1, 'UTF-8')
        . '</value></odeProperty>'
        . '</odeProperties>'
        . '<odeNavStructures>'
        . '<odeNavStructure>'
        . '<odePageId>PAGE</odePageId>'
        . '<odeParentPageId></odeParentPageId>'
        . '<odeNavStructureOrder>1</odeNavStructureOrder>'
        . '<pageName>Page</pageName>'
        . '<odeNavStructureProperties>'
        . '<odeNavStructureProperty><key>titlePage</key><value>Page</value></odeNavStructureProperty>'
        . '</odeNavStructureProperties>'
        . '<odePagStructures>'
        . '<odePagStructure>'
        . '<odePageId>PAGE</odePageId>'
        . '<odeBlockId>BLOCK</odeBlockId>'
        . '<odePagStructureOrder>1</odePagStructureOrder>'
        . '<blockName>Block</blockName>'
        . '<odeComponents>'
        . '<odeComponent>'
        . '<odePageId>PAGE</odePageId>'
        . '<odeBlockId>BLOCK</odeBlockId>'
        . '<odeIdeviceId>IDEVICE</odeIdeviceId>'
        . '<odeIdeviceTypeName>text</odeIdeviceTypeName>'
        . '<odeComponentsOrder>1</odeComponentsOrder>'
        . '<htmlView><![CDATA[<img src="{{context_path}}/image.png">]]></htmlView>'
        . '<jsonProperties><![CDATA[{}]]></jsonProperties>'
        . '<odeComponentsProperties></odeComponentsProperties>'
        . '</odeComponent>'
        . '</odeComponents>'
        . '<odePagStructureProperties></odePagStructureProperties>'
        . '</odePagStructure>'
        . '</odePagStructures>'
        . '</odeNavStructure>'
        . '</odeNavStructures>'
        . '</ode>';

    $zip = new ZipArchive();
    if ($zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Unable to create CLI fixture.');
    }

    foreach (
        [
            'content.xml' => $xml,
            'content.dtd' => '<!ELEMENT ode ANY>',
            'index.html' => '<!doctype html>',
            'screenshot.png' => 'png',
            'theme/style.css' => 'body{}',
            'libs/common.js' => '',
            'idevices/text/runtime.js' => '',
            'content/resources/image.png' => $assetContent,
        ] as $path => $contents
    ) {
        $zip->addFromString($path, $contents);
    }

    $zip->close();

    return $archivePath;
}

/**
 * Run the CLI and capture its output.
 *
 * @param array<int, string> $arguments Arguments after the binary name.
 *
 * @return array{code:int,stdout:string,stderr:string}
 */
function runCli(array $arguments): array
{
    $stdout = fopen('php://temp', 'w+b');
    $stderr = fopen('php://temp', 'w+b');

    if ($stdout === false || $stderr === false) {
        throw new RuntimeException('Unable to create CLI capture streams.');
    }

    $code = (new Application())->run(
        array_merge(['elp-parser'], $arguments),
        $stdout,
        $stderr
    );

    rewind($stdout);
    rewind($stderr);

    $output = stream_get_contents($stdout);
    $error = stream_get_contents($stderr);

    fclose($stdout);
    fclose($stderr);

    return [
        'code' => $code,
        'stdout' => $output === false ? '' : $output,
        'stderr' => $error === false ? '' : $error,
    ];
}

it(
    'shows help and rejects unknown or incomplete commands',
    function () {
        $help = runCli(['help']);
        expect($help['code'])->toBe(0);
        expect($help['stdout'])->toContain('ELP Parser CLI');

        $defaultHelp = runCli([]);
        expect($defaultHelp['code'])->toBe(0);

        $unknown = runCli(['unknown']);
        expect($unknown['code'])->toBe(2);
        expect($unknown['stderr'])->toContain('Unknown command');

        foreach (
            [
                ['inspect'],
                ['validate'],
                ['manifest'],
                ['assets'],
                ['json'],
                ['diff'],
                ['diff', 'left.elpx'],
                ['fingerprint'],
                ['extract'],
                ['extract', 'project.elpx'],
            ] as $arguments
        ) {
            expect(runCli($arguments)['code'])->toBe(2);
        }

        expect((new Application())->run(['elp-parser'], 'invalid', 'invalid'))
            ->toBe(2);
    }
);

it(
    'supports inspect validate manifest assets json and fingerprints',
    function () {
        $archive = createCliFixture();

        try {
            $inspect = runCli(['inspect', $archive]);
            expect($inspect['code'])->toBe(0);
            expect($inspect['stdout'])->toContain('Title: CLI fixture');
            expect($inspect['stdout'])->toContain('Profile: elpx-v4');

            $inspectJson = runCli(['inspect', $archive, '--json']);
            expect($inspectJson['code'])->toBe(0);
            expect(json_decode($inspectJson['stdout'], true)['title'])
                ->toBe('CLI fixture');

            $validate = runCli(['validate', $archive]);
            expect($validate['code'])->toBe(0);
            expect($validate['stdout'])->toContain('VALID');

            $validateJson = runCli(['validate', $archive, '--json']);
            expect(json_decode($validateJson['stdout'], true)['valid'])
                ->toBeTrue();

            $manifest = runCli(['manifest', $archive]);
            expect(json_decode($manifest['stdout'], true)['resourceFiles'])
                ->toContain('content/resources/image.png');

            $assets = runCli(['assets', $archive]);
            expect(json_decode($assets['stdout'], true)[0]['path'])
                ->toBe('content/resources/image.png');

            $compact = runCli(['json', $archive]);
            expect(json_decode($compact['stdout'], true)['title'])
                ->toBe('CLI fixture');

            $detailed = runCli(['json', $archive, '--detailed']);
            expect(json_decode($detailed['stdout'], true)['format']['family'])
                ->toBe('ode');

            $fingerprint = runCli(['fingerprint', $archive]);
            expect($fingerprint['stdout'])->toContain('Archive: ');
            expect($fingerprint['stdout'])->toContain('Content: ');

            $fingerprintJson = runCli(['fingerprint', $archive, '--json']);
            $hashes = json_decode($fingerprintJson['stdout'], true);
            expect($hashes['archive'])->toHaveLength(64);
            expect($hashes['content'])->toHaveLength(64);
        } finally {
            @unlink($archive);
        }
    }
);

it(
    'returns diff exit codes for equal and changed projects',
    function () {
        $left = createCliFixture('Same', 'v1');
        $same = createCliFixture('Same', 'v1');
        $changed = createCliFixture('Changed', 'v2');

        try {
            $noDiff = runCli(['diff', $left, $same]);
            expect($noDiff['code'])->toBe(0);
            expect(json_decode($noDiff['stdout'], true)['changed'])
                ->toBeFalse();

            $diff = runCli(['diff', $left, $changed]);
            expect($diff['code'])->toBe(1);
            expect(json_decode($diff['stdout'], true)['changed'])
                ->toBeTrue();
        } finally {
            @unlink($left);
            @unlink($same);
            @unlink($changed);
        }
    }
);

it(
    'extracts projects and reports parser exceptions',
    function () {
        $archive = createCliFixture();
        $destination = sys_get_temp_dir() . '/elp-cli-extract-' . uniqid('', true);

        try {
            $extract = runCli(['extract', $archive, $destination]);
            expect($extract['code'])->toBe(0);
            expect($extract['stdout'])->toContain('Extracted to');
            expect(is_file($destination . '/content.xml'))->toBeTrue();

            $failure = runCli([
                'inspect',
                sys_get_temp_dir() . '/missing-' . uniqid('', true) . '.elpx',
            ]);
            expect($failure['code'])->toBe(1);
            expect($failure['stderr'])->toContain('Error:');
        } finally {
            @unlink($archive);

            if (is_dir($destination)) {
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator(
                        $destination,
                        \FilesystemIterator::SKIP_DOTS
                    ),
                    \RecursiveIteratorIterator::CHILD_FIRST
                );

                foreach ($iterator as $item) {
                    if ($item->isDir()) {
                        rmdir($item->getPathname());
                    } else {
                        unlink($item->getPathname());
                    }
                }

                rmdir($destination);
            }
        }
    }
);
