<?php

/**
 * Additional coverage for validation and temporary input edge cases.
 *
 * @category Tests
 * @package  ELPParser
 * @author   INTEF <cedec@educacion.gob.es>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/exelearning/elp-parser
 */

namespace Exelearning\ElpParser\Tests\Unit;

use Exelearning\Archive\ArchiveLimits;
use Exelearning\Archive\ArchiveReader;
use Exelearning\Exception\ElpParserException;
use Exelearning\Exception\InvalidArchiveException;
use Exelearning\Exception\ResourceLimitException;
use Exelearning\Support\TemporaryProjectFile;
use Exelearning\Validation\SchemaValidator;
use RuntimeException;
use ZipArchive;

it(
    'reports unsupported and missing schema configuration',
    function () {
        $validator = new SchemaValidator();

        $unsupported = $validator->validate('<root/>', __FILE__, 'json');
        expect($unsupported['valid'])->toBeFalse();
        expect($unsupported['errors'][0]['code'])->toBe('unsupported_schema_type');

        $missing = $validator->validate(
            '<root/>',
            sys_get_temp_dir() . '/missing-schema-' . uniqid('', true) . '.xsd'
        );
        expect($missing['valid'])->toBeFalse();
        expect($missing['errors'][0]['code'])->toBe('schema_not_found');
    }
);

it(
    'returns libxml errors for malformed xml during schema validation',
    function () {
        $xsd = tempnam(sys_get_temp_dir(), 'schema-coverage-');

        if ($xsd === false) {
            throw new RuntimeException('Unable to create temporary schema.');
        }

        file_put_contents(
            $xsd,
            '<?xml version="1.0"?>'
            . '<xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema">'
            . '<xs:element name="root" type="xs:string"/>'
            . '</xs:schema>'
        );

        try {
            $result = (new SchemaValidator())->validate(
                '<root><broken></root>',
                $xsd
            );

            expect($result['valid'])->toBeFalse();
            expect($result['errors'])->not->toBe([]);
        } finally {
            @unlink($xsd);
        }
    }
);

it(
    'validates dtd input without an xml declaration',
    function () {
        $dtd = tempnam(sys_get_temp_dir(), 'dtd coverage ');

        if ($dtd === false) {
            throw new RuntimeException('Unable to create temporary DTD.');
        }

        file_put_contents($dtd, '<!ELEMENT root EMPTY>');

        try {
            $result = (new SchemaValidator())->validate(
                '<root/>',
                $dtd,
                SchemaValidator::TYPE_DTD
            );

            expect($result['valid'])->toBeTrue();
            expect($result['errors'])->toBe([]);
        } finally {
            @unlink($dtd);
        }
    }
);

it(
    'rejects non-resource temporary stream input',
    function () {
        expect(
            fn() => TemporaryProjectFile::fromStream(
                'not-a-stream',
                'elpx',
                1024
            )
        )->toThrow(ElpParserException::class);
    }
);

it(
    'spools stream and content input with normalized extensions',
    function () {
        $stream = fopen('php://temp', 'w+b');

        if ($stream === false) {
            throw new RuntimeException('Unable to create memory stream.');
        }

        fwrite($stream, 'stream-data');
        rewind($stream);

        $streamPath = TemporaryProjectFile::fromStream(
            $stream,
            ' .ELP-X! ',
            1024
        );
        $contentPath = TemporaryProjectFile::fromContents(
            'content-data',
            '***',
            1024
        );

        try {
            expect(file_get_contents($streamPath))->toBe('stream-data');
            expect(pathinfo($streamPath, PATHINFO_EXTENSION))->toBe('elpx');
            expect(file_get_contents($contentPath))->toBe('content-data');
            expect(pathinfo($contentPath, PATHINFO_EXTENSION))->toBe('elpx');
        } finally {
            fclose($stream);
            @unlink($streamPath);
            @unlink($contentPath);
        }
    }
);

it(
    'enforces temporary stream limits while copying',
    function () {
        $stream = fopen('php://temp', 'w+b');

        if ($stream === false) {
            throw new RuntimeException('Unable to create memory stream.');
        }

        fwrite($stream, str_repeat('x', 64));
        rewind($stream);

        try {
            expect(
                fn() => TemporaryProjectFile::fromStream(
                    $stream,
                    'elpx',
                    16
                )
            )->toThrow(ResourceLimitException::class);
        } finally {
            fclose($stream);
        }
    }
);

it(
    'covers archive reader failures and hash validation',
    function () {
        $missing = sys_get_temp_dir() . '/missing-archive-' . uniqid('', true) . '.elpx';
        $reader = new ArchiveReader($missing, new ArchiveLimits());

        expect(fn() => $reader->inspect())
            ->toThrow(InvalidArchiveException::class);

        $invalid = tempnam(sys_get_temp_dir(), 'invalid-archive-');
        if ($invalid === false) {
            throw new RuntimeException('Unable to create invalid archive.');
        }

        file_put_contents($invalid, 'not-a-zip');

        try {
            $invalidReader = new ArchiveReader($invalid, new ArchiveLimits());
            expect(fn() => $invalidReader->inspect())
                ->toThrow(InvalidArchiveException::class);
        } finally {
            @unlink($invalid);
        }

        $archive = tempnam(sys_get_temp_dir(), 'archive-reader-coverage-');
        if ($archive === false) {
            throw new RuntimeException('Unable to create archive fixture.');
        }

        @unlink($archive);
        $archive .= '.elpx';

        $zip = new ZipArchive();
        $zip->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('content.xml', '<ode version="2.0"></ode>');
        $zip->addFromString('asset.txt', str_repeat('a', 128));
        $zip->close();

        try {
            $archiveReader = new ArchiveReader($archive, new ArchiveLimits());

            expect(fn() => $archiveReader->readEntry('missing.txt'))
                ->toThrow(InvalidArchiveException::class);
            expect(fn() => $archiveReader->readEntry('asset.txt', 8))
                ->toThrow(ResourceLimitException::class);
            expect(fn() => $archiveReader->hashArchive('not-a-hash'))
                ->toThrow(InvalidArchiveException::class);
            expect(fn() => $archiveReader->hashEntry('asset.txt', 'not-a-hash'))
                ->toThrow(InvalidArchiveException::class);
            expect(fn() => $archiveReader->hashEntry('missing.txt'))
                ->toThrow(InvalidArchiveException::class);

            $entryLimitReader = new ArchiveReader(
                $archive,
                new ArchiveLimits(maxEntries: 1)
            );
            expect(fn() => $entryLimitReader->inspect())
                ->toThrow(ResourceLimitException::class);

            $sizeLimitReader = new ArchiveReader(
                $archive,
                new ArchiveLimits(maxEntryBytes: 32)
            );
            expect(fn() => $sizeLimitReader->inspect())
                ->toThrow(ResourceLimitException::class);

            $totalLimitReader = new ArchiveReader(
                $archive,
                new ArchiveLimits(maxTotalBytes: 64)
            );
            expect(fn() => $totalLimitReader->inspect())
                ->toThrow(ResourceLimitException::class);
        } finally {
            @unlink($archive);
        }
    }
);
