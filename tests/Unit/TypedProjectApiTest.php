<?php

/**
 * Tests for typed project models and lightweight inspection.
 *
 * @category Tests
 * @package  ELPParser
 * @author   INTEF <cedec@educacion.gob.es>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/exelearning/elp-parser
 */

namespace Exelearning\ElpParser\Tests\Unit;

use Exelearning\ELPParser;
use Exelearning\Model\Asset;
use Exelearning\Model\Page;
use Exelearning\Model\Project;
use Exelearning\Model\VersionInfo;

it(
    'exposes an additive typed project model',
    function () {
        $parser = ELPParser::fromFile(__DIR__ . '/../Fixtures/propiedades.elpx');
        $project = $parser->getProject();

        expect($project)->toBeInstanceOf(Project::class);
        expect($project->getTitle())->toBe('propiedades');
        expect($project->getFormatFamily())->toBe('ode');
        expect($project->getFormatVersion())->toBe('2.0');
        expect($project->getVersionInfo())->toBeInstanceOf(VersionInfo::class);
        expect($project->getPages()[0])->toBeInstanceOf(Page::class);
        expect($project->getPages()[0]->getTitle())->toBe('Propiedades idevices');
    }
);

it(
    'exposes typed assets without replacing array APIs',
    function () {
        $parser = ELPParser::fromFile(
            __DIR__ . '/../Fixtures/un-contenido-de-ejemplo-para-probar-estilos-y-catalogacion.elpx'
        );

        $assets = $parser->getProject()->getAssets();

        expect($assets)->not->toBe([]);
        expect($assets[0])->toBeInstanceOf(Asset::class);
        expect($assets[0]->getPath())->toBeString();
        expect($parser->getAssetsDetailed()[0])->toBeArray();
    }
);

it(
    'inspects modern projects without returning fully parsed content collections',
    function () {
        $info = ELPParser::inspect(
            __DIR__ . '/../Fixtures/un-contenido-de-ejemplo-para-probar-estilos-y-catalogacion.elpx'
        );

        expect($info['title'])->toBe('Un contenido de ejemplo para probar estilos y catalogación');
        expect($info['formatFamily'])->toBe('ode');
        expect($info['formatVersion'])->toBe('2.0');
        expect($info['packageProfile'])->toBe('elpx-v4');
        expect($info['version'])->toBe(4);
        expect($info)->not->toHaveKey('pages');
        expect($info)->not->toHaveKey('assets');
    }
);

it(
    'inspects legacy projects without building the full legacy page structure',
    function () {
        $info = ELPParser::inspect(__DIR__ . '/../Fixtures/04_La_Ilustracion.elp');

        expect($info['title'])->toBe('La Ilustración');
        expect($info['formatFamily'])->toBe('legacy');
        expect($info['formatVersion'])->toBeNull();
        expect($info['packageProfile'])->toBe('legacy-v2');
        expect($info['version'])->toBe(2);
        expect($info)->not->toHaveKey('pages');
        expect($info)->not->toHaveKey('assets');
    }
);
