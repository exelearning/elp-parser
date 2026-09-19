<?php

/**
 * Tests for project fingerprints and semantic diffs.
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
 * Create a minimal fingerprint/diff project fixture.
 *
 * @param string $title        Project title.
 * @param string $projectId    Project ID.
 * @param string $versionId    Project version ID.
 * @param string $exeVersion   Application version.
 * @param string $assetContent Resource bytes.
 *
 * @return string
 */
function createFingerprintFixture(
    string $title,
    string $projectId,
    string $versionId,
    string $exeVersion,
    string $assetContent
): string {
    $temporaryFile = tempnam(sys_get_temp_dir(), 'elp-fingerprint-');
    if ($temporaryFile === false) {
        throw new RuntimeException('Unable to create temporary file.');
    }

    @unlink($temporaryFile);
    $archivePath = $temporaryFile . '.elpx';

    $xml = '<?xml version="1.0" encoding="UTF-8"?>'
        . '<ode xmlns="http://www.intef.es/xsd/ode" version="2.0">'
        . '<odeResources>'
        . '<odeResource><key>odeId</key><value>' . $projectId . '</value></odeResource>'
        . '<odeResource><key>odeVersionId</key><value>' . $versionId . '</value></odeResource>'
        . '<odeResource><key>exe_version</key><value>' . $exeVersion . '</value></odeResource>'
        . '</odeResources>'
        . '<odeProperties>'
        . '<odeProperty><key>pp_title</key><value>' . $title . '</value></odeProperty>'
        . '<odeProperty><key>pp_lang</key><value>en</value></odeProperty>'
        . '</odeProperties>'
        . '<odeNavStructures></odeNavStructures>'
        . '</ode>';

    $zip = new ZipArchive();
    if ($zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Unable to create temporary ELPX archive.');
    }

    $zip->addFromString('content.xml', $xml);
    $zip->addFromString('content.dtd', '<!ELEMENT ode ANY>');
    $zip->addFromString('content/resources/data.bin', $assetContent);
    $zip->close();

    return $archivePath;
}

it(
    'separates exact archive fingerprints from normalized content fingerprints',
    function () {
        $leftPath = createFingerprintFixture(
            'Same content',
            '20260919190000AAAAAA',
            '20260919190100BBBBBB',
            '3.0',
            'asset-v1'
        );
        $rightPath = createFingerprintFixture(
            'Same content',
            '20260920190000CCCCCC',
            '20260920190100DDDDDD',
            '4.1.0',
            'asset-v1'
        );

        try {
            $left = ELPParser::fromFile($leftPath);
            $right = ELPParser::fromFile($rightPath);

            expect($left->getArchiveFingerprint())
                ->not->toBe($right->getArchiveFingerprint());
            expect($left->getContentFingerprint())
                ->toBe($right->getContentFingerprint());
            expect($left->hasSameContentAs($right))->toBeTrue();

            $diff = $left->diff($right);
            expect($diff['changed'])->toBeFalse();
            expect($diff['metadata'])->toBe([]);
            expect($diff['assets']['modified'])->toBe([]);
        } finally {
            @unlink($leftPath);
            @unlink($rightPath);
        }
    }
);

it(
    'includes project resource bytes in normalized content fingerprints',
    function () {
        $leftPath = createFingerprintFixture(
            'Resource test',
            '20260919190000AAAAAA',
            '20260919190100BBBBBB',
            '4.0.0',
            'asset-v1'
        );
        $rightPath = createFingerprintFixture(
            'Resource test',
            '20260919190000AAAAAA',
            '20260919190200CCCCCC',
            '4.0.0',
            'asset-v2'
        );

        try {
            $left = ELPParser::fromFile($leftPath);
            $right = ELPParser::fromFile($rightPath);

            expect($left->getArchiveEntryFingerprint('content/resources/data.bin'))
                ->toBe(hash('sha256', 'asset-v1'));
            expect($left->getContentFingerprint())
                ->not->toBe($right->getContentFingerprint());

            $diff = $left->diff($right);
            expect($diff['changed'])->toBeTrue();
            expect($diff['assets']['modified'])->toBe([
                'content/resources/data.bin',
            ]);
        } finally {
            @unlink($leftPath);
            @unlink($rightPath);
        }
    }
);

it(
    'reports semantic metadata changes',
    function () {
        $leftPath = createFingerprintFixture(
            'Before',
            '20260919190000AAAAAA',
            '20260919190100BBBBBB',
            '4.0.0',
            'asset-v1'
        );
        $rightPath = createFingerprintFixture(
            'After',
            '20260919190000AAAAAA',
            '20260919190200CCCCCC',
            '4.0.0',
            'asset-v1'
        );

        try {
            $diff = ELPParser::fromFile($leftPath)->diff(
                ELPParser::fromFile($rightPath)
            );

            expect($diff['changed'])->toBeTrue();
            expect($diff['metadata']['title'])->toBe([
                'before' => 'Before',
                'after' => 'After',
            ]);
            expect($diff['assets']['modified'])->toBe([]);
        } finally {
            @unlink($leftPath);
            @unlink($rightPath);
        }
    }
);
