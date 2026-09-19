<?php

/**
 * Tests for typed diagnostics and custom iDevice decoder extensions.
 *
 * @category Tests
 * @package  ELPParser
 * @author   INTEF <cedec@educacion.gob.es>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/exelearning/elp-parser
 */

namespace Exelearning\ElpParser\Tests\Unit;

use Exelearning\ELPParser;
use Exelearning\Parser\IdeviceDecoderInterface;
use Exelearning\Parser\IdeviceDecoderRegistry;
use Exelearning\Parser\OdeParser;
use Exelearning\ParserOptions;
use Exelearning\Validation\Diagnostic;
use Exelearning\Validation\ValidationResult;
use SimpleXMLElement;

it(
    'wraps validation arrays in typed diagnostics without changing the array contract',
    function () {
        $result = ValidationResult::fromArray(
            [
                'valid' => false,
                'errors' => [
                    [
                        'code' => 'missing_asset',
                        'message' => 'Missing asset.',
                        'context' => ['path' => 'missing.png'],
                    ],
                    'ignored',
                ],
                'warnings' => [
                    [
                        'code' => 'warning_code',
                        'message' => 'Warning.',
                        'context' => 'invalid-context',
                    ],
                ],
            ]
        );

        expect($result->isValid())->toBeFalse();
        expect($result->errors())->toHaveCount(1);
        expect($result->warnings())->toHaveCount(1);
        expect($result->has('missing_asset'))->toBeTrue();
        expect($result->has('warning_code'))->toBeTrue();
        expect($result->has('not-present'))->toBeFalse();

        $error = $result->errors()[0];
        expect($error)->toBeInstanceOf(Diagnostic::class);
        expect($error->getSeverity())->toBe(Diagnostic::ERROR);
        expect($error->getCode())->toBe('missing_asset');
        expect($error->getMessage())->toBe('Missing asset.');
        expect($error->getContext())->toBe(['path' => 'missing.png']);
        expect($error->jsonSerialize())->toBe($error->toArray());

        $warning = $result->warnings()[0];
        expect($warning->getSeverity())->toBe(Diagnostic::WARNING);
        expect($warning->getContext())->toBe([]);

        expect($result->toArray())->toBe([
            'valid' => false,
            'errors' => [
                [
                    'code' => 'missing_asset',
                    'message' => 'Missing asset.',
                    'context' => ['path' => 'missing.png'],
                ],
            ],
            'warnings' => [
                [
                    'code' => 'warning_code',
                    'message' => 'Warning.',
                    'context' => [],
                ],
            ],
        ]);
        expect($result->jsonSerialize())->toBe($result->toArray());

        expect((new ValidationResult())->isValid())->toBeTrue();
    }
);

it(
    'exposes typed validation from the parser facade',
    function () {
        $parser = ELPParser::fromFile(
            __DIR__ . '/../Fixtures/propiedades.elpx'
        );

        $typed = $parser->validateResult();

        expect($typed)->toBeInstanceOf(ValidationResult::class);
        expect($typed->toArray())->toBe($parser->validatePackage());
    }
);

it(
    'registers ordered custom idevice decoders and applies the first match',
    function () {
        $ignored = new class implements IdeviceDecoderInterface {
            public function supports(array $idevice): bool
            {
                return false;
            }

            public function decode(array $idevice): mixed
            {
                return ['ignored' => true];
            }
        };

        $textDecoder = new class implements IdeviceDecoderInterface {
            public function supports(array $idevice): bool
            {
                return ($idevice['type'] ?? '') === 'text';
            }

            public function decode(array $idevice): mixed
            {
                return [
                    'plainText' => strtoupper(
                        (string) ($idevice['text'] ?? '')
                    ),
                ];
            }
        };

        $registry = new IdeviceDecoderRegistry([$ignored]);
        expect($registry->register($textDecoder))->toBe($registry);
        expect($registry->all())->toHaveCount(2);
        expect($registry->decode(['type' => 'unknown']))->toBeNull();

        $xml = new SimpleXMLElement(
            '<ode xmlns="http://www.intef.es/xsd/ode" version="2.0">'
            . '<odeResources></odeResources>'
            . '<odeProperties></odeProperties>'
            . '<odeNavStructures><odeNavStructure>'
            . '<odePageId>PAGE</odePageId>'
            . '<odeParentPageId></odeParentPageId>'
            . '<odeNavStructureOrder>1</odeNavStructureOrder>'
            . '<pageName>Page</pageName>'
            . '<odeNavStructureProperties></odeNavStructureProperties>'
            . '<odePagStructures><odePagStructure>'
            . '<odePageId>PAGE</odePageId>'
            . '<odeBlockId>BLOCK</odeBlockId>'
            . '<odePagStructureOrder>1</odePagStructureOrder>'
            . '<blockName>Block</blockName>'
            . '<odeComponents><odeComponent>'
            . '<odePageId>PAGE</odePageId>'
            . '<odeBlockId>BLOCK</odeBlockId>'
            . '<odeIdeviceId>IDEVICE</odeIdeviceId>'
            . '<odeIdeviceTypeName>text</odeIdeviceTypeName>'
            . '<odeComponentsOrder>1</odeComponentsOrder>'
            . '<htmlView><![CDATA[<p>Hello decoder</p>]]></htmlView>'
            . '<jsonProperties><![CDATA[{}]]></jsonProperties>'
            . '<odeComponentsProperties></odeComponentsProperties>'
            . '</odeComponent></odeComponents>'
            . '<odePagStructureProperties></odePagStructureProperties>'
            . '</odePagStructure></odePagStructures>'
            . '</odeNavStructure></odeNavStructures>'
            . '</ode>'
        );

        $parsed = (new OdeParser(decoderRegistry: $registry))->parse($xml);
        $idevice = $parsed['pages'][0]['idevices'][0];

        expect($idevice['customDecoder'])->toBe(get_class($textDecoder));
        expect($idevice['customData'])->toBe([
            'plainText' => 'HELLO DECODER',
        ]);

        $options = new ParserOptions(ideviceDecoders: $registry);
        expect($options->ideviceDecoders)->toBe($registry);
    }
);
