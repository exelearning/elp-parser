<?php

/**
 * LegacyParser.php
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
use Exelearning\Support\Slugger;
use SimpleXMLElement;

/**
 * Parse legacy contentv3.xml project data.
 *
 * @category Parser
 * @package  Exelearning
 * @author   INTEF <cedec@educacion.gob.es>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/exelearning/elp-parser
 */
class LegacyParser
{
    private Slugger $slugger;
    private HtmlText $htmlText;

    /**
     * @param Slugger|null  $slugger  Optional slugger.
     * @param HtmlText|null $htmlText Optional HTML text converter.
     */
    public function __construct(?Slugger $slugger = null, ?HtmlText $htmlText = null)
    {
        $this->slugger = $slugger ?? new Slugger();
        $this->htmlText = $htmlText ?? new HtmlText();
    }

    /**
     * Parse a legacy XML document.
     *
     * @param SimpleXMLElement $xml Parsed XML.
     *
     * @return array<string, mixed>
     */
    public function parse(SimpleXMLElement $xml): array
    {
        $data = $this->parseElement($xml);
        $legacyData = is_array($data) ? $data : [];
        $pages = [];

        if (isset($legacyData['_root']) && is_array($legacyData['_root'])) {
            $this->collectPages($legacyData['_root'], 0, $pages);
        }

        return [
            'data' => $legacyData,
            'title' => (string) ($legacyData['_title'] ?? ''),
            'description' => (string) ($legacyData['_description'] ?? ''),
            'author' => (string) ($legacyData['_author'] ?? ''),
            'license' => (string) ($legacyData['license'] ?? ''),
            'language' => (string) ($legacyData['_lang'] ?? ''),
            'learningResourceType' => (string) ($legacyData['_learningResourceType'] ?? ''),
            'strings' => $this->recursiveStringExtraction($xml),
            'pages' => $pages,
        ];
    }

    /**
     * Recursively parse a contentv3 value.
     *
     * @param SimpleXMLElement $element XML element.
     *
     * @return mixed
     */
    private function parseElement(SimpleXMLElement $element): mixed
    {
        $name = $element->getName();

        switch ($name) {
            case 'unicode':
            case 'string':
                return (string) $element['value'];
            case 'int':
                return (int) $element['value'];
            case 'bool':
                return ((string) $element['value']) === '1';
            case 'list':
                $list = [];
                foreach ($element->children() as $child) {
                    $list[] = $this->parseElement($child);
                }
                return $list;
            case 'dictionary':
                $dict = [];
                $key = null;
                foreach ($element->children() as $child) {
                    $childName = $child->getName();
                    if (
                        ($childName === 'string' || $childName === 'unicode')
                        && (string) $child['role'] === 'key'
                    ) {
                        $key = (string) $child['value'];
                    } elseif ($key !== null) {
                        $dict[$key] = $this->parseElement($child);
                        $key = null;
                    }
                }
                return $dict;
            case 'instance':
                return isset($element->dictionary) ? $this->parseElement($element->dictionary) : [];
            case 'none':
                return null;
            case 'reference':
                return ['ref' => (string) $element['key']];
            default:
                return [];
        }
    }

    /**
     * Recursively collect non-empty XML strings.
     *
     * @param SimpleXMLElement $element XML element.
     *
     * @return array<int, string>
     */
    private function recursiveStringExtraction(SimpleXMLElement $element): array
    {
        $strings = [];
        $elementArray = (array) $element;

        foreach ($elementArray as $value) {
            if (is_string($value) && trim($value) !== '') {
                $strings[] = trim($value);
                continue;
            }

            if ($value instanceof SimpleXMLElement) {
                $strings = array_merge($strings, $this->recursiveStringExtraction($value));
                continue;
            }

            if (!is_array($value)) {
                continue;
            }

            foreach ($value as $subValue) {
                if ($subValue instanceof SimpleXMLElement) {
                    $strings = array_merge($strings, $this->recursiveStringExtraction($subValue));
                }
            }
        }

        return array_values(array_unique($strings));
    }

    /**
     * Build normalized page information.
     *
     * @param array<string, mixed>              $node  Legacy page node.
     * @param int                               $level Current depth.
     * @param array<int, array<string, mixed>> &$pages Collected pages.
     *
     * @return void
     */
    private function collectPages(array $node, int $level, array &$pages): void
    {
        $title = (string) ($node['_title'] ?? '');
        $filename = $level === 0 ? 'index.html' : $this->slugger->slug($title) . '.html';
        $idevices = [];

        if (isset($node['idevices']) && is_array($node['idevices'])) {
            foreach ($node['idevices'] as $idevice) {
                if (!is_array($idevice)) {
                    continue;
                }

                $html = '';
                if (isset($idevice['fields']) && is_array($idevice['fields'])) {
                    foreach ($idevice['fields'] as $field) {
                        if (is_array($field) && isset($field['content_w_resourcePaths'])) {
                            $html = (string) $field['content_w_resourcePaths'];
                            break;
                        }
                    }
                }

                $idevices[] = [
                    'id' => $idevice['_id'] ?? '',
                    'type' => $idevice['_iDeviceDir'] ?? ($idevice['class_'] ?? ''),
                    'title' => $idevice['_title'] ?? '',
                    'text' => $this->htmlText->convert($html),
                    'html' => $html,
                    'jsonProperties' => [],
                    'visible' => true,
                    'teacherOnly' => false,
                ];
            }
        }

        $pages[] = [
            'id' => $node['_id'] ?? '',
            'parentId' => is_array($node['parent'] ?? null) ? '' : ($node['parent'] ?? ''),
            'filename' => $filename,
            'title' => $title,
            'pageName' => $title,
            'level' => $level,
            'visible' => true,
            'highlight' => false,
            'hidePageTitle' => false,
            'editableInPage' => false,
            'blocks' => [],
            'idevices' => $idevices,
        ];

        if (!isset($node['children']) || !is_array($node['children'])) {
            return;
        }

        foreach ($node['children'] as $child) {
            if (is_array($child)) {
                $this->collectPages($child, $level + 1, $pages);
            }
        }
    }
}
