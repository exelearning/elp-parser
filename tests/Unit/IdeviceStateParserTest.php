<?php

/**
 * Tests for normalized iDevice state parsing.
 *
 * @category Tests
 * @package  ELPParser
 * @author   INTEF <cedec@educacion.gob.es>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/exelearning/elp-parser
 */

namespace Exelearning\ElpParser\Tests\Unit;

use Exelearning\Asset\AssetReferenceExtractor;
use Exelearning\Parser\IdeviceStateParser;
use Exelearning\Parser\OdeParser;
use SimpleXMLElement;

it(
    'parses all four modern idevice storage patterns',
    function () {
        $xml = <<<'XML'
<ode xmlns="http://www.intef.es/xsd/ode" version="2.0">
  <odeNavStructures>
    <odeNavStructure>
      <odePageId>PAGE</odePageId>
      <odeParentPageId></odeParentPageId>
      <odeNavStructureOrder>1</odeNavStructureOrder>
      <pageName>Page</pageName>
      <odeNavStructureProperties></odeNavStructureProperties>
      <odePagStructures>
        <odePagStructure>
          <odePageId>PAGE</odePageId>
          <odeBlockId>BLOCK</odeBlockId>
          <odePagStructureOrder>1</odePagStructureOrder>
          <blockName>Block</blockName>
          <odeComponents>
            <odeComponent>
              <odePageId>PAGE</odePageId>
              <odeBlockId>BLOCK</odeBlockId>
              <odeIdeviceId>STANDARD</odeIdeviceId>
              <odeIdeviceTypeName>text</odeIdeviceTypeName>
              <odeComponentsOrder>1</odeComponentsOrder>
              <htmlView><![CDATA[<p>Hello</p>]]></htmlView>
              <jsonProperties><![CDATA[{"message":"hello"}]]></jsonProperties>
              <odeComponentsProperties></odeComponentsProperties>
            </odeComponent>
            <odeComponent>
              <odePageId>PAGE</odePageId>
              <odeBlockId>BLOCK</odeBlockId>
              <odeIdeviceId>GAME</odeIdeviceId>
              <odeIdeviceTypeName>flipcards</odeIdeviceTypeName>
              <odeComponentsOrder>2</odeComponentsOrder>
              <htmlView><![CDATA[
                <div class="flipcards-DataGame js-hidden">%7B%22cardsGame%22%3A%5B%7B%22url%22%3A%22%7B%7Bcontext_path%7D%7D%2Fcard.jpg%22%7D%5D%7D</div>
              ]]></htmlView>
              <jsonProperties><![CDATA[{"ideviceId":"GAME"}]]></jsonProperties>
              <odeComponentsProperties></odeComponentsProperties>
            </odeComponent>
            <odeComponent>
              <odePageId>PAGE</odePageId>
              <odeBlockId>BLOCK</odeBlockId>
              <odeIdeviceId>VIDEO</odeIdeviceId>
              <odeIdeviceTypeName>interactive-video</odeIdeviceTypeName>
              <odeComponentsOrder>3</odeComponentsOrder>
              <htmlView><![CDATA[
                <script id="exe-interactive-video-contents" type="application/json">{"slides":[{"type":"text"}]}</script>
              ]]></htmlView>
              <jsonProperties><![CDATA[{"ideviceId":"VIDEO"}]]></jsonProperties>
              <odeComponentsProperties></odeComponentsProperties>
            </odeComponent>
            <odeComponent>
              <odePageId>PAGE</odePageId>
              <odeBlockId>BLOCK</odeBlockId>
              <odeIdeviceId>HTML</odeIdeviceId>
              <odeIdeviceTypeName>rubric</odeIdeviceTypeName>
              <odeComponentsOrder>4</odeComponentsOrder>
              <htmlView><![CDATA[<table><tr><td>Rubric</td></tr></table>]]></htmlView>
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

        $parsed = (new OdeParser())->parse(new SimpleXMLElement($xml));
        $idevices = $parsed['pages'][0]['idevices'];

        expect($idevices[0]['storagePattern'])->toBe(IdeviceStateParser::PATTERN_STANDARD_JSON);
        expect($idevices[0]['data']['message'])->toBe('hello');
        expect($idevices[0]['jsonPropertiesRaw'])->toBe('{"message":"hello"}');

        expect($idevices[1]['storagePattern'])->toBe(IdeviceStateParser::PATTERN_DATA_GAME);
        expect($idevices[1]['data']['cardsGame'][0]['url'])->toBe('{{context_path}}/card.jpg');

        expect($idevices[2]['storagePattern'])->toBe(IdeviceStateParser::PATTERN_EMBEDDED_JSON);
        expect($idevices[2]['data']['slides'][0]['type'])->toBe('text');

        expect($idevices[3]['storagePattern'])->toBe(IdeviceStateParser::PATTERN_HTML_ONLY);
        expect($idevices[3]['data'])->toBe([]);
    }
);

it(
    'reports malformed normalized state without failing the whole project parse',
    function () {
        $state = (new IdeviceStateParser())->parse(
            '<div class="flipcards-DataGame js-hidden">%7Bbroken</div>',
            ''
        );

        expect($state['storagePattern'])->toBe(IdeviceStateParser::PATTERN_DATA_GAME);
        expect($state['data'])->toBe([]);
        expect($state['decodeError'])->toBeString();
    }
);

it(
    'discovers assets from decoded game state',
    function () {
        $extractor = new AssetReferenceExtractor(
            ['content/resources/card.jpg']
        );

        $assets = $extractor->extract(
            [
                [
                    'id' => 'PAGE',
                    'title' => 'Page',
                    'idevices' => [
                        [
                            'id' => 'GAME',
                            'type' => 'flipcards',
                            'html' => '',
                            'jsonProperties' => [],
                            'data' => [
                                'cardsGame' => [
                                    ['url' => '{{context_path}}/card.jpg'],
                                ],
                            ],
                        ],
                    ],
                ],
            ]
        );

        expect(array_column($assets, 'path'))->toBe([
            'content/resources/card.jpg',
        ]);
    }
);
