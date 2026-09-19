<?php

/**
 * OdeParser.php
 *
 * PHP Version 8.0
 *
 * @category Parser
 * @package  Exelearning
 * @author   INTEF <cedec@educacion.gob.es>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/exelearning/elp-parser
 */

namespace Exelearning\Parser;

use Exelearning\Support\HtmlText;
use SimpleXMLElement;

/**
 * Parse modern ODE content.xml project data.
 *
 * @category Parser
 * @package  Exelearning
 * @author   INTEF <cedec@educacion.gob.es>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/exelearning/elp-parser
 */
class OdeParser
{
    private HtmlText $htmlText;
    private IdeviceStateParser $stateParser;

    /**
     * @param HtmlText|null            $htmlText    Optional HTML text converter.
     * @param IdeviceStateParser|null  $stateParser Optional iDevice state parser.
     */
    public function __construct(
        ?HtmlText $htmlText = null,
        ?IdeviceStateParser $stateParser = null
    ) {
        $this->htmlText = $htmlText ?? new HtmlText();
        $this->stateParser = $stateParser ?? new IdeviceStateParser();
    }

    /**
     * Parse a modern ODE XML document.
     *
     * @param SimpleXMLElement $xml Parsed XML.
     *
     * @return array<string, mixed>
     */
    public function parse(SimpleXMLElement $xml): array
    {
        $userPreferences = $this->readKeyValueNodes(
            $this->xpath($xml, './x:userPreferences/x:userPreference')
        );
        $resources = $this->readKeyValueNodes($this->xpath($xml, './x:odeResources/x:odeResource'));
        $properties = $this->readKeyValueNodes($this->xpath($xml, './x:odeProperties/x:odeProperty'));
        $pages = $this->collectPages($xml);

        return [
            'schemaVersion' => isset($xml['version']) ? (string) $xml['version'] : null,
            'userPreferences' => $userPreferences,
            'resources' => $resources,
            'properties' => $properties,
            'title' => (string) ($properties['pp_title'] ?? ''),
            'description' => (string) ($properties['pp_description'] ?? ''),
            'author' => (string) ($properties['pp_author'] ?? ''),
            'license' => (string) ($properties['pp_license'] ?? ($properties['license'] ?? '')),
            'language' => (string) ($properties['pp_lang'] ?? ($properties['lom_general_language'] ?? '')),
            'learningResourceType' => (string) ($properties['pp_learningResourceType'] ?? ''),
            'exeVersion' => $resources['exe_version']
                ?? ($resources['eXeVersion'] ?? ($properties['pp_exelearning_version'] ?? null)),
            'pages' => $pages,
            'strings' => $this->collectStrings($pages),
        ];
    }

    /**
     * Execute XPath with the default namespace mapped to x.
     *
     * @param SimpleXMLElement $node XML node.
     * @param string           $path XPath expression.
     *
     * @return array<int, SimpleXMLElement>
     */
    private function xpath(SimpleXMLElement $node, string $path): array
    {
        $namespaces = $node->getDocNamespaces(true);
        if (isset($namespaces[''])) {
            $node->registerXPathNamespace('x', $namespaces['']);
        } else {
            $path = str_replace('x:', '', $path);
        }

        $result = $node->xpath($path);

        return is_array($result) ? $result : [];
    }

    /**
     * Convert ODE key/value nodes to an associative array.
     *
     * @param array<int, SimpleXMLElement> $nodes Nodes to read.
     *
     * @return array<string, string>
     */
    private function readKeyValueNodes(array $nodes): array
    {
        $values = [];

        foreach ($nodes as $node) {
            $key = isset($node->key) ? trim((string) $node->key) : '';
            if ($key === '') {
                continue;
            }

            $values[$key] = isset($node->value) ? trim((string) $node->value) : '';
        }

        return $values;
    }

    /**
     * Build normalized page, block and iDevice information.
     *
     * @param SimpleXMLElement $xml Parsed XML.
     *
     * @return array<int, array<string, mixed>>
     */
    private function collectPages(SimpleXMLElement $xml): array
    {
        $pages = [];
        $nodes = $this->xpath($xml, './x:odeNavStructures/x:odeNavStructure');

        foreach ($nodes as $node) {
            $pageProperties = $this->readKeyValueNodes(
                $this->xpath($node, './x:odeNavStructureProperties/x:odeNavStructureProperty')
            );

            $blocks = [];
            $idevices = [];

            foreach ($this->xpath($node, './x:odePagStructures/x:odePagStructure') as $block) {
                $blockProperties = $this->readKeyValueNodes(
                    $this->xpath($block, './x:odePagStructureProperties/x:odePagStructureProperty')
                );
                $components = [];

                foreach ($this->xpath($block, './x:odeComponents/x:odeComponent') as $component) {
                    $componentProperties = $this->readKeyValueNodes(
                        $this->xpath($component, './x:odeComponentsProperties/x:odeComponentsProperty')
                    );
                    $html = isset($component->htmlView) ? trim((string) $component->htmlView) : '';
                    $jsonPropertiesRaw = isset($component->jsonProperties)
                        ? trim((string) $component->jsonProperties)
                        : '';
                    $state = $this->stateParser->parse($html, $jsonPropertiesRaw);

                    $componentData = [
                        'id' => isset($component->odeIdeviceId) ? (string) $component->odeIdeviceId : '',
                        'type' => isset($component->odeIdeviceTypeName) ? (string) $component->odeIdeviceTypeName : '',
                        'order' => isset($component->odeComponentsOrder) ? (int) $component->odeComponentsOrder : 0,
                        'text' => $this->htmlText->convert($html),
                        'html' => $html,
                        'jsonPropertiesRaw' => $jsonPropertiesRaw,
                        'jsonProperties' => $this->decodeJsonProperties($jsonPropertiesRaw),
                        'storagePattern' => $state['storagePattern'],
                        'data' => $state['data'],
                        'stateDecodeError' => $state['decodeError'],
                        'visible' => ($componentProperties['visibility'] ?? 'true') !== 'false',
                        'teacherOnly' => ($componentProperties['teacherOnly'] ?? 'false') === 'true',
                        'identifier' => $componentProperties['identifier'] ?? '',
                        'cssClass' => $componentProperties['cssClass'] ?? '',
                    ];

                    $components[] = $componentData;
                    $idevices[] = $componentData;
                }

                $blocks[] = [
                    'id' => isset($block->odeBlockId) ? (string) $block->odeBlockId : '',
                    'pageId' => isset($block->odePageId) ? (string) $block->odePageId : '',
                    'name' => isset($block->blockName) ? (string) $block->blockName : '',
                    'iconName' => isset($block->iconName) ? (string) $block->iconName : '',
                    'order' => isset($block->odePagStructureOrder) ? (int) $block->odePagStructureOrder : 0,
                    'visible' => ($blockProperties['visibility'] ?? 'true') !== 'false',
                    'teacherOnly' => ($blockProperties['teacherOnly'] ?? 'false') === 'true',
                    'allowToggle' => ($blockProperties['allowToggle'] ?? 'true') !== 'false',
                    'minimized' => ($blockProperties['minimized'] ?? 'false') === 'true',
                    'identifier' => $blockProperties['identifier'] ?? '',
                    'cssClass' => $blockProperties['cssClass'] ?? '',
                    'components' => $components,
                ];
            }

            $pages[] = [
                'id' => isset($node->odePageId) ? (string) $node->odePageId : '',
                'parentId' => isset($node->odeParentPageId) ? (string) $node->odeParentPageId : '',
                'title' => $pageProperties['titlePage'] ?? ((string) ($node->pageName ?? '')),
                'pageName' => isset($node->pageName) ? (string) $node->pageName : '',
                'nodeTitle' => $pageProperties['titleNode'] ?? '',
                'description' => $pageProperties['description'] ?? '',
                'order' => isset($node->odeNavStructureOrder) ? (int) $node->odeNavStructureOrder : 0,
                'visible' => ($pageProperties['visibility'] ?? 'true') !== 'false',
                'highlight' => ($pageProperties['highlight'] ?? 'false') === 'true',
                'hidePageTitle' => ($pageProperties['hidePageTitle'] ?? 'false') === 'true',
                'editableInPage' => ($pageProperties['editableInPage'] ?? 'false') === 'true',
                'titleHtml' => $pageProperties['titleHtml'] ?? '',
                'blocks' => $blocks,
                'idevices' => $idevices,
            ];
        }

        usort(
            $pages,
            static fn(array $left, array $right): int => ($left['order'] ?? 0) <=> ($right['order'] ?? 0)
        );

        return $pages;
    }

    /**
     * Decode structured iDevice properties.
     *
     * @param string $json Raw JSON.
     *
     * @return array<mixed>
     */
    private function decodeJsonProperties(string $json): array
    {
        if ($json === '') {
            return [];
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Collect normalized strings from parsed pages.
     *
     * @param array<int, array<string, mixed>> $pages Parsed pages.
     *
     * @return array<int, string>
     */
    private function collectStrings(array $pages): array
    {
        $strings = [];

        foreach ($pages as $page) {
            foreach (['title', 'pageName', 'nodeTitle', 'description'] as $field) {
                if (!empty($page[$field])) {
                    $strings[] = trim((string) $page[$field]);
                }
            }

            foreach (($page['blocks'] ?? []) as $block) {
                if (!is_array($block)) {
                    continue;
                }

                if (!empty($block['name'])) {
                    $strings[] = trim((string) $block['name']);
                }

                foreach (($block['components'] ?? []) as $component) {
                    if (is_array($component) && !empty($component['text'])) {
                        $strings[] = trim((string) $component['text']);
                    }
                }
            }
        }

        return array_values(array_unique(array_filter($strings, static fn(string $value): bool => $value !== '')));
    }
}
