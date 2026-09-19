<?php

/**
 * Coverage tests for typed model wrappers and compatibility shims.
 *
 * @category Tests
 * @package  ELPParser
 * @author   INTEF <cedec@educacion.gob.es>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/exelearning/elp-parser
 */

namespace Exelearning\ElpParser\Tests\Unit;

use Exelearning\Archive\ArchiveLimits;
use Exelearning\Model\Asset;
use Exelearning\Model\Block;
use Exelearning\Model\Idevice;
use Exelearning\Model\Page;
use Exelearning\Model\Project;
use Exelearning\Model\VersionInfo;
use InvalidArgumentException;

it(
    'fully exposes typed asset values and json serialization',
    function () {
        $data = [
            'path' => 'content/resources/image.png',
            'type' => 'image',
            'occurrences' => 3,
        ];
        $asset = new Asset($data);

        expect($asset->getPath())->toBe('content/resources/image.png');
        expect($asset->getType())->toBe('image');
        expect($asset->getOccurrences())->toBe(3);
        expect($asset->toArray())->toBe($data);
        expect($asset->jsonSerialize())->toBe($data);

        $empty = new Asset([]);
        expect($empty->getPath())->toBe('');
        expect($empty->getType())->toBe('');
        expect($empty->getOccurrences())->toBe(0);
    }
);

it(
    'fully exposes typed idevice values including state errors',
    function () {
        $data = [
            'id' => 'IDEVICE',
            'type' => 'flipcards',
            'storagePattern' => 'data-game',
            'data' => ['cards' => 2],
            'stateDecodeError' => 'invalid json',
        ];
        $idevice = new Idevice($data);

        expect($idevice->getId())->toBe('IDEVICE');
        expect($idevice->getType())->toBe('flipcards');
        expect($idevice->getStoragePattern())->toBe('data-game');
        expect($idevice->getState())->toBe(['cards' => 2]);
        expect($idevice->getStateDecodeError())->toBe('invalid json');
        expect($idevice->toArray())->toBe($data);
        expect($idevice->jsonSerialize())->toBe($data);

        $empty = new Idevice([]);
        expect($empty->getId())->toBe('');
        expect($empty->getType())->toBe('');
        expect($empty->getStoragePattern())->toBe('');
        expect($empty->getState())->toBe([]);
        expect($empty->getStateDecodeError())->toBeNull();

        $blankError = new Idevice(['stateDecodeError' => '']);
        expect($blankError->getStateDecodeError())->toBeNull();
    }
);

it(
    'fully exposes typed blocks pages and nested wrappers',
    function () {
        $ideviceData = [
            'id' => 'IDEVICE',
            'type' => 'text',
            'storagePattern' => 'standard-json',
            'data' => ['text' => 'Hello'],
        ];
        $blockData = [
            'id' => 'BLOCK',
            'name' => 'Content',
            'components' => [$ideviceData, 'ignored'],
        ];
        $pageData = [
            'id' => 'PAGE',
            'parentId' => 'ROOT',
            'title' => 'Page title',
            'blocks' => [$blockData, 'ignored'],
            'idevices' => [$ideviceData, 'ignored'],
        ];

        $block = new Block($blockData);
        expect($block->getId())->toBe('BLOCK');
        expect($block->getName())->toBe('Content');
        expect($block->getIdevices())->toHaveCount(1);
        expect($block->getIdevices()[0])->toBeInstanceOf(Idevice::class);
        expect($block->toArray())->toBe($blockData);
        expect($block->jsonSerialize())->toBe($blockData);

        $emptyBlock = new Block([]);
        expect($emptyBlock->getId())->toBe('');
        expect($emptyBlock->getName())->toBe('');
        expect($emptyBlock->getIdevices())->toBe([]);

        $page = new Page($pageData);
        expect($page->getId())->toBe('PAGE');
        expect($page->getParentId())->toBe('ROOT');
        expect($page->getTitle())->toBe('Page title');
        expect($page->getBlocks())->toHaveCount(1);
        expect($page->getBlocks()[0])->toBeInstanceOf(Block::class);
        expect($page->getIdevices())->toHaveCount(1);
        expect($page->getIdevices()[0])->toBeInstanceOf(Idevice::class);
        expect($page->toArray())->toBe($pageData);
        expect($page->jsonSerialize())->toBe($pageData);

        $emptyPage = new Page([]);
        expect($emptyPage->getId())->toBe('');
        expect($emptyPage->getParentId())->toBe('');
        expect($emptyPage->getTitle())->toBe('');
        expect($emptyPage->getBlocks())->toBe([]);
        expect($emptyPage->getIdevices())->toBe([]);
    }
);

it(
    'fully exposes version info defaults and declared values',
    function () {
        $data = [
            'declared' => '4.1.0',
            'declaredMajor' => 4,
            'detectedMajor' => 4,
            'source' => 'metadata',
        ];
        $version = new VersionInfo($data);

        expect($version->getDeclaredVersion())->toBe('4.1.0');
        expect($version->getDeclaredMajor())->toBe(4);
        expect($version->getDetectedMajor())->toBe(4);
        expect($version->getSource())->toBe('metadata');
        expect($version->toArray())->toBe($data);
        expect($version->jsonSerialize())->toBe($data);

        $empty = new VersionInfo([
            'declared' => '',
            'declaredMajor' => '4',
        ]);
        expect($empty->getDeclaredVersion())->toBeNull();
        expect($empty->getDeclaredMajor())->toBeNull();
        expect($empty->getDetectedMajor())->toBe(0);
        expect($empty->getSource())->toBe('');
    }
);

it(
    'fully exposes project model defaults collections and serialization',
    function () {
        $data = [
            'summary' => ['title' => 'Typed project'],
            'format' => ['family' => 'ode', 'version' => '2.0'],
            'versionInfo' => [
                'declared' => '4.0.0',
                'declaredMajor' => 4,
                'detectedMajor' => 4,
                'source' => 'metadata',
            ],
            'pages' => [
                ['id' => 'PAGE', 'title' => 'Page'],
                'ignored',
            ],
            'assets' => [
                ['path' => 'content/resources/a.png', 'type' => 'image'],
                'ignored',
            ],
        ];
        $project = new Project($data);

        expect($project->getTitle())->toBe('Typed project');
        expect($project->getFormatFamily())->toBe('ode');
        expect($project->getFormatVersion())->toBe('2.0');
        expect($project->getVersionInfo())->toBeInstanceOf(VersionInfo::class);
        expect($project->getPages())->toHaveCount(1);
        expect($project->getPages()[0])->toBeInstanceOf(Page::class);
        expect($project->getAssets())->toHaveCount(1);
        expect($project->getAssets()[0])->toBeInstanceOf(Asset::class);
        expect($project->toArray())->toBe($data);
        expect($project->jsonSerialize())->toBe($data);

        $empty = new Project([
            'format' => ['version' => ''],
            'versionInfo' => 'invalid',
            'pages' => ['ignored'],
            'assets' => ['ignored'],
        ]);
        expect($empty->getTitle())->toBe('');
        expect($empty->getFormatFamily())->toBe('');
        expect($empty->getFormatVersion())->toBeNull();
        expect($empty->getVersionInfo()->getDetectedMajor())->toBe(0);
        expect($empty->getPages())->toBe([]);
        expect($empty->getAssets())->toBe([]);
    }
);

it(
    'rejects invalid archive limit values',
    function () {
        expect(fn() => new ArchiveLimits(maxEntries: 0))
            ->toThrow(InvalidArgumentException::class);
        expect(fn() => new ArchiveLimits(maxEntryBytes: 0))
            ->toThrow(InvalidArgumentException::class);
        expect(fn() => new ArchiveLimits(maxTotalBytes: 0))
            ->toThrow(InvalidArgumentException::class);
        expect(fn() => new ArchiveLimits(maxXmlBytes: 0))
            ->toThrow(InvalidArgumentException::class);
        expect(fn() => new ArchiveLimits(maxCompressionRatio: 0.0))
            ->toThrow(InvalidArgumentException::class);
    }
);

it(
    'executes the historical direct include compatibility loader',
    function () {
        require __DIR__ . '/../../src/ElpParser.php';

        expect(class_exists(\Exelearning\ELPParser::class))->toBeTrue();
    }
);
