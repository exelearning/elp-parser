<?php

/**
 * Tests for indexed parser lookups and cached aggregate diagnostics.
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

it(
    'keeps repeated indexed lookups behaviorally stable',
    function () {
        $parser = ELPParser::fromFile(__DIR__ . '/../Fixtures/propiedades.elpx');
        $pageId = (string) $parser->getPages()[0]['id'];
        $blockId = (string) $parser->getBlocks()[0]['id'];
        $ideviceId = (string) $parser->getIdevices()[0]['id'];

        for ($iteration = 0; $iteration < 100; $iteration++) {
            expect($parser->getPageById($pageId)['id'])->toBe($pageId);
            expect($parser->getBlockById($blockId)['id'])->toBe($blockId);
            expect($parser->getIdeviceById($ideviceId)['id'])->toBe($ideviceId);
        }

        expect($parser->getBlocks())->toBe($parser->getBlocks());
        expect($parser->getIdevices())->toBe($parser->getIdevices());
        expect($parser->getPageTree())->toBe($parser->getPageTree());
        expect($parser->getPackageManifest())->toBe($parser->getPackageManifest());
        expect($parser->getBrokenReferences())->toBe($parser->getBrokenReferences());
        expect($parser->getInternalLinks())->toBe($parser->getInternalLinks());
    }
);

it(
    'does not double count standard json state after normalization',
    function () {
        $extractor = new AssetReferenceExtractor(
            ['content/resources/image.jpg']
        );

        $assets = $extractor->extract(
            [
                [
                    'id' => 'PAGE',
                    'title' => 'Page',
                    'idevices' => [
                        [
                            'id' => 'TEXT',
                            'type' => 'text',
                            'html' => '',
                            'storagePattern' => 'standard-json',
                            'jsonProperties' => [
                                'image' => '{{context_path}}/image.jpg',
                            ],
                            'data' => [
                                'image' => '{{context_path}}/image.jpg',
                            ],
                        ],
                    ],
                ],
            ]
        );

        expect($assets)->toHaveCount(1);
        expect($assets[0]['path'])->toBe('content/resources/image.jpg');
        expect($assets[0]['occurrences'])->toBe(1);
    }
);
