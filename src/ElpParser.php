<?php

/**
 * ElpParser.php
 *
 * PHP Version 8.1
 *
 * @category Parser
 * @package  Exelearning
 * @author   INTEF <cedec@educacion.gob.es>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/exelearning/elp-parser
 */

namespace Exelearning;

use Exception;
use JsonSerializable;
use SimpleXMLElement;
use ZipArchive;

/**
 * Parser for eXeLearning project files.
 *
 * Supported project formats:
 * - Legacy .elp packages based on contentv3.xml from eXeLearning 2.x
 * - Modern .elp/.elpx packages based on content.xml (ODE 2.0) from eXeLearning 3+
 *
 * @category Parser
 * @package  Exelearning
 * @author   INTEF <cedec@educacion.gob.es>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/exelearning/elp-parser
 */
class ELPParser implements JsonSerializable
{
    /**
     * Path to the project file.
     *
     * @var string
     */
    protected string $filePath;

    /**
     * Detected eXeLearning major version.
     *
     * Legacy projects are reported as version 2. Modern ODE-based projects
     * are treated as version 3+ and default to 3 when the package does not
     * expose a higher major version explicitly.
     *
     * @var int
     */
    protected int $version = 2;

    /**
     * Source file extension, usually elp or elpx.
     *
     * @var string
     */
    protected string $sourceExtension = '';

    /**
     * Project content format.
     *
     * Possible values:
     * - legacy-contentv3
     * - ode-content
     *
     * @var string
     */
    protected string $contentFormat = '';

    /**
     * XML entry name inside the archive.
     *
     * @var string
     */
    protected string $contentFile = '';

    /**
     * ODE schema version when available.
     *
     * @var string|null
     */
    protected ?string $contentSchemaVersion = null;

    /**
     * Raw eXeLearning version string when available.
     *
     * @var string|null
     */
    protected ?string $exeVersion = null;

    /**
     * Archive file list.
     *
     * @var array<int, string>
     */
    protected array $archiveEntries = [];

    /**
     * Whether the package includes a root content.dtd file.
     *
     * @var bool
     */
    protected bool $hasRootDtd = false;

    /**
     * Detected resource layout family.
     *
     * Possible values:
     * - content-resources
     * - legacy-temp-paths
     * - mixed
     * - none
     *
     * @var string
     */
    protected string $resourceLayout = 'none';

    /**
     * Parsed legacy dictionary data.
     *
     * @var array
     */
    protected array $legacyData = [];

    /**
     * Parsed modern ODE properties.
     *
     * @var array
     */
    protected array $odeProperties = [];

    /**
     * Parsed modern ODE resources.
     *
     * @var array
     */
    protected array $odeResources = [];

    /**
     * Raw extracted strings.
     *
     * @var array
     */
    protected array $strings = [];

    /**
     * Parsed page information.
     *
     * @var array
     */
    protected array $pages = [];

    /**
     * Referenced assets found in content.
     *
     * @var array
     */
    protected array $assets = [];

    /**
     * Detailed referenced asset information.
     *
     * @var array
     */
    protected array $assetsDetailed = [];

    /**
     * Title of the project.
     *
     * @var string
     */
    protected string $title = '';

    /**
     * Description of the project.
     *
     * @var string
     */
    protected string $description = '';

    /**
     * Author of the project.
     *
     * @var string
     */
    protected string $author = '';

    /**
     * License of the project.
     *
     * @var string
     */
    protected string $license = '';

    /**
     * Language of the project.
     *
     * @var string
     */
    protected string $language = '';

    /**
     * Learning resource type.
     *
     * @var string
     */
    protected string $learningResourceType = '';

    /**
     * Create a new parser instance.
     *
     * @param string $filePath Path to the project file
     *
     * @throws Exception If file cannot be opened or is invalid
     * @return void
     */
    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
        $this->sourceExtension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $this->parse();
    }

    /**
     * Create a parser from a file path.
     *
     * @param string $filePath Path to the project file
     *
     * @throws Exception If file cannot be opened or is invalid
     * @return self
     */
    public static function fromFile(string $filePath): self
    {
        return new self($filePath);
    }

    /**
     * Detect the project format and parse its contents.
     *
     * @throws Exception If file parsing fails
     * @return void
     */
    protected function parse(): void
    {
        if (!file_exists($this->filePath)) {
            throw new Exception('File does not exist.');
        }

        $zip = new ZipArchive();
        if ($zip->open($this->filePath) !== true) {
            throw new Exception('The file is not a valid ZIP file.');
        }

        $this->archiveEntries = $this->readArchiveEntries($zip);
        $this->hasRootDtd = in_array('content.dtd', $this->archiveEntries, true);
        $this->resourceLayout = $this->detectResourceLayoutFromArchiveEntries($this->archiveEntries);

        if ($zip->locateName('contentv3.xml') !== false) {
            $this->contentFormat = 'legacy-contentv3';
            $this->contentFile = 'contentv3.xml';
            $this->version = 2;
        } elseif ($zip->locateName('content.xml') !== false) {
            $this->contentFormat = 'ode-content';
            $this->contentFile = 'content.xml';
        } else {
            $zip->close();
            throw new Exception('Invalid ELP file: No content XML found.');
        }

        $xmlContent = $zip->getFromName($this->contentFile);
        $zip->close();

        if ($xmlContent === false) {
            throw new Exception('Failed to read XML content.');
        }

        $xml = $this->loadXml($xmlContent);

        if ($this->contentFormat === 'legacy-contentv3') {
            $this->parseLegacyXml($xml);
            return;
        }

        $this->parseModernXml($xml);
    }

    /**
     * Parse a legacy contentv3.xml project.
     *
     * @param SimpleXMLElement $xml Parsed XML document
     *
     * @return void
     */
    protected function parseLegacyXml(SimpleXMLElement $xml): void
    {
        $data = $this->parseElement($xml);
        $this->legacyData = is_array($data) ? $data : [];

        $this->title = $this->legacyData['_title'] ?? '';
        $this->description = $this->legacyData['_description'] ?? '';
        $this->author = $this->legacyData['_author'] ?? '';
        $this->license = $this->legacyData['license'] ?? '';
        $this->language = $this->legacyData['_lang'] ?? '';
        $this->learningResourceType = $this->legacyData['_learningResourceType'] ?? '';

        $this->strings = $this->recursiveStringExtraction($xml);

        if (isset($this->legacyData['_root']) && is_array($this->legacyData['_root'])) {
            $pages = [];
            $this->collectLegacyPages($this->legacyData['_root'], 0, $pages);
            $this->pages = $pages;
            $this->assetsDetailed = $this->extractDetailedAssetsFromPages($pages);
            $this->assets = $this->flattenAssetPaths($this->assetsDetailed);
        }
    }

    /**
     * Parse a modern ODE project based on content.xml.
     *
     * @param SimpleXMLElement $xml Parsed XML document
     *
     * @return void
     */
    protected function parseModernXml(SimpleXMLElement $xml): void
    {
        $this->contentSchemaVersion = isset($xml['version']) ? (string) $xml['version'] : null;
        $this->odeResources = $this->readModernKeyValueNodes($this->xpath($xml, './x:odeResources/x:odeResource'));
        $this->odeProperties = $this->readModernKeyValueNodes($this->xpath($xml, './x:odeProperties/x:odeProperty'));

        $this->title = $this->odeProperties['pp_title'] ?? '';
        $this->description = $this->odeProperties['pp_description'] ?? '';
        $this->author = $this->odeProperties['pp_author'] ?? '';
        $this->license = $this->odeProperties['pp_license'] ?? ($this->odeProperties['license'] ?? '');
        $this->language = $this->odeProperties['pp_lang'] ?? ($this->odeProperties['lom_general_language'] ?? '');
        $this->learningResourceType = $this->odeProperties['pp_learningResourceType'] ?? '';

        $this->exeVersion = $this->odeResources['exe_version']
            ?? ($this->odeProperties['pp_exelearning_version'] ?? null);
        $this->version = $this->detectModernVersion($this->exeVersion);

        $this->pages = $this->collectModernPages($xml);
        $this->strings = $this->collectModernStrings($this->pages);
        $this->assetsDetailed = $this->extractDetailedAssetsFromPages($this->pages);
        $this->assets = $this->flattenAssetPaths($this->assetsDetailed);
    }

    /**
     * Load XML content with hardened libxml settings.
     *
     * @param string $xmlContent XML content
     *
     * @throws Exception If XML parsing fails
     * @return SimpleXMLElement
     */
    protected function loadXml(string $xmlContent): SimpleXMLElement
    {
        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlContent, SimpleXMLElement::class, LIBXML_NONET);

        if ($xml === false) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            $message = isset($errors[0]) ? trim($errors[0]->message) : 'Unknown XML parsing error.';
            throw new Exception('XML Parsing error: ' . $message);
        }

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $xml;
    }

    /**
     * Execute an XPath query with the default namespace mapped to x.
     *
     * @param SimpleXMLElement $node XML node
     * @param string           $path XPath expression
     *
     * @return array
     */
    protected function xpath(SimpleXMLElement $node, string $path): array
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
     * Read ZIP entry names.
     *
     * @param ZipArchive $zip Open ZIP archive
     *
     * @return array
     */
    protected function readArchiveEntries(ZipArchive $zip): array
    {
        $entries = [];

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);
            if ($name !== false) {
                $entries[] = $name;
            }
        }

        return $entries;
    }

    /**
     * Convert modern ODE key/value collections into an associative array.
     *
     * @param array $nodes Nodes with key/value children
     *
     * @return array
     */
    protected function readModernKeyValueNodes(array $nodes): array
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
     * Detect the eXeLearning major version for modern projects.
     *
     * @param string|null $exeVersion Raw version string
     *
     * @return int
     */
    protected function detectModernVersion(?string $exeVersion): int
    {
        if ($exeVersion === null || $exeVersion === '') {
            return $this->isLikelyVersion4Package() ? 4 : 3;
        }

        if (preg_match('/(?:^|[^0-9])([3-9])(?:\.[0-9]+)?/', $exeVersion, $matches) === 1) {
            $detected = (int) $matches[1];

            if ($detected <= 3 && $this->isLikelyVersion4Package()) {
                return 4;
            }

            return $detected;
        }

        return $this->isLikelyVersion4Package() ? 4 : 3;
    }

    /**
     * Detect the resource layout family from archive entries.
     *
     * @param array $entries ZIP entry names
     *
     * @return string
     */
    protected function detectResourceLayoutFromArchiveEntries(array $entries): string
    {
        $hasContentResources = false;
        $hasLegacyTempPaths = false;

        foreach ($entries as $entry) {
            if (str_starts_with($entry, 'content/resources/')) {
                $hasContentResources = true;
            }

            if (str_starts_with($entry, 'files/tmp/')) {
                $hasLegacyTempPaths = true;
            }
        }

        if ($hasContentResources && $hasLegacyTempPaths) {
            return 'mixed';
        }

        if ($hasContentResources) {
            return 'content-resources';
        }

        if ($hasLegacyTempPaths) {
            return 'legacy-temp-paths';
        }

        return 'none';
    }

    /**
     * Recursively extract all text strings from XML.
     *
     * @param SimpleXMLElement $element XML element to extract from
     *
     * @return array
     */
    protected function recursiveStringExtraction(SimpleXMLElement $element): array
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
     * Recursively parse a contentv3 structure.
     *
     * @param SimpleXMLElement $element XML element
     *
     * @return mixed
     */
    protected function parseElement(SimpleXMLElement $element): mixed
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
                    if (($childName === 'string' || $childName === 'unicode') && (string) $child['role'] === 'key') {
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
     * Build page information for legacy projects.
     *
     * @param array $node  Node information
     * @param int   $level Current depth level
     * @param array $pages Accumulated pages
     *
     * @return void
     */
    protected function collectLegacyPages(array $node, int $level, array &$pages): void
    {
        $title = $node['_title'] ?? '';
        $filename = $level === 0 ? 'index.html' : $this->slug($title) . '.html';

        $idevices = [];
        if (isset($node['idevices']) && is_array($node['idevices'])) {
            foreach ($node['idevices'] as $idevice) {
                $html = '';
                if (isset($idevice['fields']) && is_array($idevice['fields'])) {
                    foreach ($idevice['fields'] as $field) {
                        if (isset($field['content_w_resourcePaths'])) {
                            $html = (string) $field['content_w_resourcePaths'];
                            break;
                        }
                    }
                }

                $idevices[] = [
                    'id' => $idevice['_id'] ?? '',
                    'type' => $idevice['_iDeviceDir'] ?? ($idevice['class_'] ?? ''),
                    'title' => $idevice['_title'] ?? '',
                    'text' => $this->htmlToText($html),
                    'html' => $html,
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
                $this->collectLegacyPages($child, $level + 1, $pages);
            }
        }
    }

    /**
     * Build page information for ODE projects.
     *
     * @param SimpleXMLElement $xml Parsed XML document
     *
     * @return array
     */
    protected function collectModernPages(SimpleXMLElement $xml): array
    {
        $pages = [];
        $nodes = $this->xpath($xml, './x:odeNavStructures/x:odeNavStructure');

        foreach ($nodes as $node) {
            $pageProperties = $this->readModernKeyValueNodes(
                $this->xpath($node, './x:odeNavStructureProperties/x:odeNavStructureProperty')
            );

            $blocks = [];
            $idevices = [];

            foreach ($this->xpath($node, './x:odePagStructures/x:odePagStructure') as $block) {
                $blockProperties = $this->readModernKeyValueNodes(
                    $this->xpath($block, './x:odePagStructureProperties/x:odePagStructureProperty')
                );

                $components = [];

                foreach ($this->xpath($block, './x:odeComponents/x:odeComponent') as $component) {
                    $componentProperties = $this->readModernKeyValueNodes(
                        $this->xpath($component, './x:odeComponentsProperties/x:odeComponentsProperty')
                    );

                    $html = isset($component->htmlView) ? trim((string) $component->htmlView) : '';
                    $componentData = [
                        'id' => isset($component->odeIdeviceId) ? (string) $component->odeIdeviceId : '',
                        'type' => isset($component->odeIdeviceTypeName) ? (string) $component->odeIdeviceTypeName : '',
                        'order' => isset($component->odeComponentsOrder) ? (int) $component->odeComponentsOrder : 0,
                        'text' => $this->htmlToText($html),
                        'html' => $html,
                        'jsonProperties' => $this->decodeJsonProperties(
                            isset($component->jsonProperties) ? (string) $component->jsonProperties : ''
                        ),
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
     * Decode JSON component properties.
     *
     * @param string $json Raw JSON text
     *
     * @return array
     */
    protected function decodeJsonProperties(string $json): array
    {
        if ($json === '') {
            return [];
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Convert HTML to plain text.
     *
     * @param string $html HTML fragment
     *
     * @return string
     */
    protected function htmlToText(string $html): string
    {
        if ($html === '') {
            return '';
        }

        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text);

        return trim((string) $text);
    }

    /**
     * Extract unique strings from parsed modern pages.
     *
     * @param array $pages Page information
     *
     * @return array
     */
    protected function collectModernStrings(array $pages): array
    {
        $strings = [];

        foreach ($pages as $page) {
            foreach (['title', 'pageName', 'nodeTitle', 'description'] as $field) {
                if (!empty($page[$field])) {
                    $strings[] = trim((string) $page[$field]);
                }
            }

            foreach ($page['blocks'] as $block) {
                if (!empty($block['name'])) {
                    $strings[] = trim((string) $block['name']);
                }

                foreach ($block['components'] as $component) {
                    if (!empty($component['text'])) {
                        $strings[] = trim((string) $component['text']);
                    }
                }
            }
        }

        return array_values(array_unique(array_filter($strings, static fn($value): bool => $value !== '')));
    }

    /**
     * Extract referenced asset paths from page HTML.
     *
     * @param array $pages Page information
     *
     * @return array
     */
    protected function extractDetailedAssetsFromPages(array $pages): array
    {
        $assets = [];

        foreach ($pages as $page) {
            foreach ($page['idevices'] as $idevice) {
                if (empty($idevice['html'])) {
                    continue;
                }

                preg_match_all(
                    '/(?:\{\{context_path\}\}\/)?([A-Za-z0-9_\/.\-]+\.(?:png|jpe?g|gif|svg|webp|bmp|mp3|wav|ogg|m4a|mp4|webm|ogv|pdf|docx?|xlsx?|pptx?|odt|ods|odp|zip))/i',
                    $idevice['html'],
                    $matches
                );

                foreach ($matches[1] as $match) {
                    $path = ltrim($match, '/');
                    $assets[$path] ??= [
                        'path' => $path,
                        'type' => $this->detectAssetType($path),
                        'extension' => strtolower((string) pathinfo($path, PATHINFO_EXTENSION)),
                        'pages' => [],
                        'idevices' => [],
                        'occurrences' => 0,
                    ];

                    $assets[$path]['pages'][$page['id'] ?: $page['title']] = [
                        'id' => $page['id'] ?? '',
                        'title' => $page['title'] ?? '',
                    ];
                    $assets[$path]['idevices'][$idevice['id'] ?: ($page['id'] . ':' . $idevice['type'])] = [
                        'id' => $idevice['id'] ?? '',
                        'type' => $idevice['type'] ?? '',
                        'pageId' => $page['id'] ?? '',
                        'pageTitle' => $page['title'] ?? '',
                    ];
                    $assets[$path]['occurrences']++;
                }
            }
        }

        foreach ($assets as &$asset) {
            $asset['pages'] = array_values($asset['pages']);
            $asset['idevices'] = array_values($asset['idevices']);
        }
        unset($asset);

        ksort($assets);

        return array_values($assets);
    }

    /**
     * Flatten detailed assets to a sorted path list.
     *
     * @param array $assetsDetailed Detailed assets
     *
     * @return array
     */
    protected function flattenAssetPaths(array $assetsDetailed): array
    {
        $paths = array_map(static fn(array $asset): string => $asset['path'], $assetsDetailed);
        sort($paths);

        return $paths;
    }

    /**
     * Detect the logical asset type from a file path.
     *
     * @param string $path Asset path
     *
     * @return string
     */
    protected function detectAssetType(string $path): string
    {
        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'bmp' => 'image',
            'mp3', 'wav', 'ogg', 'm4a' => 'audio',
            'mp4', 'webm', 'ogv' => 'video',
            'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp' => 'document',
            'zip' => 'archive',
            default => 'other',
        };
    }

    /**
     * Get the detected eXeLearning major version.
     *
     * @return int
     */
    public function getVersion(): int
    {
        return $this->version;
    }

    /**
     * Get the source project extension.
     *
     * @return string
     */
    public function getSourceExtension(): string
    {
        return $this->sourceExtension;
    }

    /**
     * Get the detected content format identifier.
     *
     * @return string
     */
    public function getContentFormat(): string
    {
        return $this->contentFormat;
    }

    /**
     * Get the XML entry name used by the package.
     *
     * @return string
     */
    public function getContentFile(): string
    {
        return $this->contentFile;
    }

    /**
     * Get the ODE schema version when available.
     *
     * @return string|null
     */
    public function getContentSchemaVersion(): ?string
    {
        return $this->contentSchemaVersion;
    }

    /**
     * Get the raw eXeLearning version string when available.
     *
     * @return string|null
     */
    public function getExeVersion(): ?string
    {
        return $this->exeVersion;
    }

    /**
     * Determine whether the project uses the legacy contentv3 format.
     *
     * @return bool
     */
    public function isLegacyFormat(): bool
    {
        return $this->contentFormat === 'legacy-contentv3';
    }

    /**
     * Get all extracted strings.
     *
     * @return array
     */
    public function getStrings(): array
    {
        return $this->strings;
    }

    /**
     * Get parsed page information.
     *
     * @return array
     */
    public function getPages(): array
    {
        return $this->pages;
    }

    /**
     * Get only visible pages.
     *
     * @return array
     */
    public function getVisiblePages(): array
    {
        return array_values(
            array_filter(
                $this->pages,
                static fn(array $page): bool => ($page['visible'] ?? true) === true
            )
        );
    }

    /**
     * Get all blocks across all pages.
     *
     * @return array
     */
    public function getBlocks(): array
    {
        $blocks = [];

        foreach ($this->pages as $page) {
            foreach ($page['blocks'] ?? [] as $block) {
                $blocks[] = $block + [
                    'pageTitle' => $page['title'] ?? '',
                ];
            }
        }

        return $blocks;
    }

    /**
     * Get all idevices across all pages.
     *
     * @return array
     */
    public function getIdevices(): array
    {
        $idevices = [];

        foreach ($this->pages as $page) {
            foreach ($page['idevices'] ?? [] as $idevice) {
                $idevices[] = $idevice + [
                    'pageId' => $page['id'] ?? '',
                    'pageTitle' => $page['title'] ?? '',
                ];
            }
        }

        return $idevices;
    }

    /**
     * Get grouped text content for each page.
     *
     * @return array
     */
    public function getPageTexts(): array
    {
        $pageTexts = [];

        foreach ($this->pages as $page) {
            $texts = [];

            foreach ($page['idevices'] ?? [] as $idevice) {
                $text = trim((string) ($idevice['text'] ?? ''));
                if ($text !== '') {
                    $texts[] = $text;
                }
            }

            $pageTexts[] = [
                'id' => $page['id'] ?? '',
                'title' => $page['title'] ?? '',
                'pageName' => $page['pageName'] ?? '',
                'visible' => $page['visible'] ?? true,
                'texts' => $texts,
                'text' => trim(implode("\n\n", $texts)),
            ];
        }

        return $pageTexts;
    }

    /**
     * Get grouped text content for visible pages only.
     *
     * @return array
     */
    public function getVisiblePageTexts(): array
    {
        return array_values(
            array_filter(
                $this->getPageTexts(),
                static fn(array $pageText): bool => ($pageText['visible'] ?? true) === true
            )
        );
    }

    /**
     * Get grouped text content for a single page by its ID.
     *
     * @param string $pageId Page identifier
     *
     * @return array|null
     */
    public function getPageTextById(string $pageId): ?array
    {
        foreach ($this->getPageTexts() as $pageText) {
            if (($pageText['id'] ?? '') === $pageId) {
                return $pageText;
            }
        }

        return null;
    }

    /**
     * Get idevices marked as teacher-only.
     *
     * @return array
     */
    public function getTeacherOnlyIdevices(): array
    {
        return array_values(
            array_filter(
                $this->getIdevices(),
                static fn(array $idevice): bool => ($idevice['teacherOnly'] ?? false) === true
            )
        );
    }

    /**
     * Get hidden idevices.
     *
     * @return array
     */
    public function getHiddenIdevices(): array
    {
        return array_values(
            array_filter(
                $this->getIdevices(),
                static fn(array $idevice): bool => ($idevice['visible'] ?? true) === false
            )
        );
    }

    /**
     * Get asset paths referenced by the parsed content.
     *
     * @return array
     */
    public function getAssets(): array
    {
        return $this->assets;
    }

    /**
     * Get detailed asset information.
     *
     * @return array
     */
    public function getAssetsDetailed(): array
    {
        return $this->assetsDetailed;
    }

    /**
     * Get image asset paths.
     *
     * @return array
     */
    public function getImages(): array
    {
        return $this->filterAssetPathsByType('image');
    }

    /**
     * Get audio asset paths.
     *
     * @return array
     */
    public function getAudioFiles(): array
    {
        return $this->filterAssetPathsByType('audio');
    }

    /**
     * Get video asset paths.
     *
     * @return array
     */
    public function getVideoFiles(): array
    {
        return $this->filterAssetPathsByType('video');
    }

    /**
     * Get document asset paths.
     *
     * @return array
     */
    public function getDocuments(): array
    {
        return $this->filterAssetPathsByType('document');
    }

    /**
     * Get archive assets that are present in the ZIP but not referenced in parsed content.
     *
     * @return array
     */
    public function getOrphanAssets(): array
    {
        $referenced = array_fill_keys($this->assets, true);
        $orphans = [];

        foreach ($this->archiveEntries as $entry) {
            if (str_ends_with($entry, '/')) {
                continue;
            }

            $type = $this->detectAssetType($entry);
            if (!in_array($type, ['image', 'audio', 'video', 'document', 'archive'], true)) {
                continue;
            }

            if (!isset($referenced[$entry])) {
                $orphans[] = $entry;
            }
        }

        sort($orphans);

        return $orphans;
    }

    /**
     * Filter asset paths by logical type.
     *
     * @param string $type Asset type
     *
     * @return array
     */
    protected function filterAssetPathsByType(string $type): array
    {
        $paths = [];

        foreach ($this->assetsDetailed as $asset) {
            if (($asset['type'] ?? null) === $type) {
                $paths[] = $asset['path'];
            }
        }

        sort($paths);

        return $paths;
    }

    /**
     * Get archive entry names.
     *
     * @return array
     */
    public function getArchiveEntries(): array
    {
        return $this->archiveEntries;
    }

    /**
     * Return whether the package contains a root content.dtd entry.
     *
     * @return bool
     */
    public function hasRootDtd(): bool
    {
        return $this->hasRootDtd;
    }

    /**
     * Return the detected resource layout family.
     *
     * @return string
     */
    public function getResourceLayout(): string
    {
        return $this->resourceLayout;
    }

    /**
     * Heuristic detection for likely eXeLearning 4-style packages.
     *
     * The package format alone does not always expose the exact major version.
     * In practice, `.elpx` plus a root `content.dtd` is a useful signal for
     * newer packages even when embedded metadata still reports `3.0`.
     *
     * @return bool
     */
    public function isLikelyVersion4Package(): bool
    {
        return $this->contentFormat === 'ode-content'
            && $this->sourceExtension === 'elpx'
            && $this->hasRootDtd;
    }

    /**
     * Get the title.
     *
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Get the description.
     *
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Get the author.
     *
     * @return string
     */
    public function getAuthor(): string
    {
        return $this->author;
    }

    /**
     * Get the license.
     *
     * @return string
     */
    public function getLicense(): string
    {
        return $this->license;
    }

    /**
     * Get the language.
     *
     * @return string
     */
    public function getLanguage(): string
    {
        return $this->language;
    }

    /**
     * Get the learning resource type.
     *
     * @return string
     */
    public function getLearningResourceType(): string
    {
        return $this->learningResourceType;
    }

    /**
     * Convert parser data to an array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'version' => $this->version,
            'title' => $this->title,
            'description' => $this->description,
            'author' => $this->author,
            'license' => $this->license,
            'language' => $this->language,
            'learningResourceType' => $this->learningResourceType,
            'strings' => $this->strings,
        ];
    }

    /**
     * JSON serialization method.
     *
     * @return array
     */
    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    /**
     * Export parsed data as JSON string or file.
     *
     * @param string|null $destinationPath Optional path to save the JSON file
     *
     * @throws Exception If the file cannot be written
     * @return string
     */
    public function exportJson(?string $destinationPath = null): string
    {
        $json = json_encode($this, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            throw new Exception('Failed to encode JSON: ' . json_last_error_msg());
        }

        if ($destinationPath !== null && file_put_contents($destinationPath, $json) === false) {
            throw new Exception('Unable to write JSON file.');
        }

        return $json;
    }

    /**
     * Get detailed metadata information.
     *
     * @return array
     */
    public function getMetadata(): array
    {
        if ($this->isLegacyFormat()) {
            return [
                'metadata' => $this->buildLegacyMetadata(),
            ];
        }

        return [
            'metadata' => $this->buildModernMetadata(),
        ];
    }

    /**
     * Build normalized legacy metadata output.
     *
     * @return array
     */
    protected function buildLegacyMetadata(): array
    {
        $data = $this->legacyData;

        $meta = [
            [
                'schema' => 'Package',
                'content' => [
                    'title' => $data['_title'] ?? '',
                    'lang' => $data['_lang'] ?? '',
                    'description' => [
                        'general_description' => $data['_description'] ?? '',
                        'objectives' => $data['_objectives'] ?? '',
                        'preknowledge' => $data['_preknowledge'] ?? '',
                    ],
                    'author' => $data['_author'] ?? '',
                    'license' => $data['license'] ?? '',
                    'learningResourceType' => $data['_learningResourceType'] ?? '',
                    'usage' => [
                        'intendedEndUserRoleType' => $data['_intendedEndUserRoleType'] ?? '',
                        'intendedEndUserRoleGroup' => $data['_intendedEndUserRoleGroup'] ?? '',
                        'intendedEndUserRoleTutor' => $data['_intendedEndUserRoleTutor'] ?? '',
                        'contextPlace' => $data['_contextPlace'] ?? '',
                        'contextMode' => $data['_contextMode'] ?? '',
                    ],
                    'project_properties' => [
                        'backgroundImg' => $data['_backgroundImg'] ?? '',
                        'backgroundImgTile' => $data['backgroundImgTile'] ?? '',
                        'footer' => $data['footer'] ?? '',
                    ],
                    'format' => [
                        'Doctype' => $data['_docType'] ?? '',
                    ],
                    'taxonomy' => [
                        'level_1' => $data['_levelNames'][0] ?? '',
                        'level_2' => $data['_levelNames'][1] ?? '',
                        'level_3' => $data['_levelNames'][2] ?? '',
                    ],
                    'advanced_options' => [
                        'custom_head' => $data['_extraHeadContent'] ?? '',
                    ],
                ],
            ],
        ];

        foreach (['dublinCore' => 'Dublin core', 'lom' => 'LOM v1.0', 'lomEs' => 'LOM-ES v1.0'] as $key => $schema) {
            if (isset($data[$key])) {
                $meta[] = [
                    'schema' => $schema,
                    'content' => $data[$key] ?? [],
                ];
            }
        }

        return $meta;
    }

    /**
     * Build normalized modern metadata output.
     *
     * @return array
     */
    protected function buildModernMetadata(): array
    {
        return [
            [
                'schema' => 'Package',
                'content' => [
                    'title' => $this->title,
                    'lang' => $this->language,
                    'description' => [
                        'general_description' => $this->description,
                        'objectives' => '',
                        'preknowledge' => '',
                    ],
                    'author' => $this->author,
                    'license' => $this->license,
                    'learningResourceType' => $this->learningResourceType,
                    'format' => [
                        'container' => $this->sourceExtension,
                        'content_file' => $this->contentFile,
                        'content_format' => $this->contentFormat,
                        'schema_version' => $this->contentSchemaVersion ?? '',
                        'resource_layout' => $this->resourceLayout,
                        'has_root_dtd' => $this->hasRootDtd,
                        'likely_version_4' => $this->isLikelyVersion4Package(),
                    ],
                    'project_properties' => $this->odeProperties,
                    'project_resources' => $this->odeResources,
                ],
            ],
        ];
    }

    /**
     * Create a filename-friendly slug from a string.
     *
     * @param string $text Input text
     *
     * @return string
     */
    protected function slug(string $text): string
    {
        $slug = removeAccents($text);
        $slug = strtolower($slug);
        $slug = preg_replace('/[^a-z0-9]+/', '_', $slug);

        return trim((string) $slug, '_');
    }

    /**
     * Extract the project contents to a directory.
     *
     * Extraction is performed entry by entry to block path traversal attempts.
     *
     * @param string $destinationPath Directory to extract contents to
     *
     * @throws Exception If extraction fails
     * @return void
     */
    public function extract(string $destinationPath): void
    {
        $zip = new ZipArchive();

        if ($zip->open($this->filePath) !== true) {
            throw new Exception('Unable to open ELP/ELPX file for extraction.');
        }

        if (!file_exists($destinationPath) && !mkdir($destinationPath, 0755, true) && !is_dir($destinationPath)) {
            $zip->close();
            throw new Exception('Unable to create destination directory.');
        }

        $destinationRoot = realpath($destinationPath);
        if ($destinationRoot === false) {
            $zip->close();
            throw new Exception('Unable to resolve destination directory.');
        }

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $entryName = $zip->getNameIndex($index);
            if ($entryName === false) {
                continue;
            }

            if ($this->isUnsafeArchivePath($entryName)) {
                $zip->close();
                throw new Exception('Unsafe ZIP entry detected: ' . $entryName);
            }

            $targetPath = $destinationRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $entryName);

            if (str_ends_with($entryName, '/')) {
                if (!is_dir($targetPath) && !mkdir($targetPath, 0755, true) && !is_dir($targetPath)) {
                    $zip->close();
                    throw new Exception('Unable to create directory during extraction.');
                }
                continue;
            }

            $targetDir = dirname($targetPath);
            if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
                $zip->close();
                throw new Exception('Unable to create directory during extraction.');
            }

            $stream = $zip->getStream($entryName);
            if ($stream === false) {
                $zip->close();
                throw new Exception('Unable to read ZIP entry: ' . $entryName);
            }

            $contents = stream_get_contents($stream);
            fclose($stream);

            if ($contents === false || file_put_contents($targetPath, $contents) === false) {
                $zip->close();
                throw new Exception('Unable to extract ZIP entry: ' . $entryName);
            }
        }

        $zip->close();
    }

    /**
     * Check if a ZIP entry path is unsafe.
     *
     * @param string $entryName ZIP entry name
     *
     * @return bool
     */
    protected function isUnsafeArchivePath(string $entryName): bool
    {
        if ($entryName === '' || str_starts_with($entryName, '/') || preg_match('/^[A-Za-z]:[\/\\\\]/', $entryName) === 1) {
            return true;
        }

        foreach (preg_split('#[\/\\\\]+#', $entryName) as $segment) {
            if ($segment === '..') {
                return true;
            }
        }

        return false;
    }
}

/**
 * Remove accents from a string using WordPress\' implementation.
 *
 * This function is copied from WordPress 6.8.1 and retains its original
 * copyright notice.
 *
 * @param string $text   Text that might have accent characters.
 * @param string $locale Optional. The locale to use for accent removal.
 *
 * @return string Filtered string with replaced characters.
 *
 * @author  WordPress contributors
 * @license GPL-2.0-or-later
 * @see     https://github.com/WordPress/wordpress-develop/blob/6.8.1/src/wp-includes/formatting.php
 */
function removeAccents(string $text, string $locale = ''): string
{
    if (!preg_match('/[\x80-\xff]/', $text)) {
        return $text;
    }

    if (seemsUtf8($text)) {
        if (function_exists('normalizer_is_normalized') && function_exists('normalizer_normalize')) {
            if (!normalizer_is_normalized($text)) {
                $text = normalizer_normalize($text);
            }
        }

        $chars = [
            'ª' => 'a', 'º' => 'o', 'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A',
            'Ä' => 'A', 'Å' => 'A', 'Æ' => 'AE', 'Ç' => 'C', 'È' => 'E', 'É' => 'E',
            'Ê' => 'E', 'Ë' => 'E', 'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'Ð' => 'D', 'Ñ' => 'N', 'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O',
            'Ö' => 'O', 'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ý' => 'Y',
            'Þ' => 'TH', 'ß' => 's', 'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a',
            'ä' => 'a', 'å' => 'a', 'æ' => 'ae', 'ç' => 'c', 'è' => 'e', 'é' => 'e',
            'ê' => 'e', 'ë' => 'e', 'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ð' => 'd', 'ñ' => 'n', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o',
            'ö' => 'o', 'ø' => 'o', 'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
            'ý' => 'y', 'þ' => 'th', 'ÿ' => 'y', 'Ø' => 'O', 'Ā' => 'A', 'ā' => 'a',
            'Ă' => 'A', 'ă' => 'a', 'Ą' => 'A', 'ą' => 'a', 'Ć' => 'C', 'ć' => 'c',
            'Ĉ' => 'C', 'ĉ' => 'c', 'Ċ' => 'C', 'ċ' => 'c', 'Č' => 'C', 'č' => 'c',
            'Ď' => 'D', 'ď' => 'd', 'Đ' => 'D', 'đ' => 'd', 'Ē' => 'E', 'ē' => 'e',
            'Ĕ' => 'E', 'ĕ' => 'e', 'Ė' => 'E', 'ė' => 'e', 'Ę' => 'E', 'ę' => 'e',
            'Ě' => 'E', 'ě' => 'e', 'Ĝ' => 'G', 'ĝ' => 'g', 'Ğ' => 'G', 'ğ' => 'g',
            'Ġ' => 'G', 'ġ' => 'g', 'Ģ' => 'G', 'ģ' => 'g', 'Ĥ' => 'H', 'ĥ' => 'h',
            'Ħ' => 'H', 'ħ' => 'h', 'Ĩ' => 'I', 'ĩ' => 'i', 'Ī' => 'I', 'ī' => 'i',
            'Ĭ' => 'I', 'ĭ' => 'i', 'Į' => 'I', 'į' => 'i', 'İ' => 'I', 'ı' => 'i',
            'Ĳ' => 'IJ','ĳ' => 'ij','Ĵ' => 'J', 'ĵ' => 'j', 'Ķ' => 'K', 'ķ' => 'k',
            'ĸ' => 'k', 'Ĺ' => 'L', 'ĺ' => 'l', 'Ļ' => 'L', 'ļ' => 'l', 'Ľ' => 'L',
            'ľ' => 'l', 'Ŀ' => 'L', 'ŀ' => 'l', 'Ł' => 'L', 'ł' => 'l', 'Ń' => 'N',
            'ń' => 'n', 'Ņ' => 'N', 'ņ' => 'n', 'Ň' => 'N', 'ň' => 'n', 'ŉ' => 'n',
            'Ŋ' => 'N', 'ŋ' => 'n', 'Ō' => 'O', 'ō' => 'o', 'Ŏ' => 'O', 'ŏ' => 'o',
            'Ő' => 'O', 'ő' => 'o', 'Œ' => 'OE','œ' => 'oe','Ŕ' => 'R', 'ŕ' => 'r',
            'Ŗ' => 'R', 'ŗ' => 'r', 'Ř' => 'R', 'ř' => 'r', 'Ś' => 'S', 'ś' => 's',
            'Ŝ' => 'S', 'ŝ' => 's', 'Ş' => 'S', 'ş' => 's', 'Š' => 'S', 'š' => 's',
            'Ţ' => 'T', 'ţ' => 't', 'Ť' => 'T', 'ť' => 't', 'Ŧ' => 'T', 'ŧ' => 't',
            'Ũ' => 'U', 'ũ' => 'u', 'Ū' => 'U', 'ū' => 'u', 'Ŭ' => 'U', 'ŭ' => 'u',
            'Ů' => 'U', 'ů' => 'u', 'Ű' => 'U', 'ű' => 'u', 'Ų' => 'U', 'ų' => 'u',
            'Ŵ' => 'W', 'ŵ' => 'w', 'Ŷ' => 'Y', 'ŷ' => 'y', 'Ÿ' => 'Y', 'Ź' => 'Z',
            'ź' => 'z', 'Ż' => 'Z', 'ż' => 'z', 'Ž' => 'Z', 'ž' => 'z', 'ſ' => 's',
            'Ə' => 'E', 'ǝ' => 'e', 'Ș' => 'S', 'ș' => 's', 'Ț' => 'T', 'ț' => 't',
            '€' => 'E', '£' => '', 'Ơ' => 'O', 'ơ' => 'o', 'Ư' => 'U', 'ư' => 'u',
            'Ầ' => 'A', 'ầ' => 'a', 'Ằ' => 'A', 'ằ' => 'a', 'Ề' => 'E', 'ề' => 'e',
            'Ồ' => 'O', 'ồ' => 'o', 'Ờ' => 'O', 'ờ' => 'o', 'Ừ' => 'U', 'ừ' => 'u',
            'Ỳ' => 'Y', 'ỳ' => 'y', 'Ả' => 'A', 'ả' => 'a', 'Ẩ' => 'A', 'ẩ' => 'a',
            'Ẳ' => 'A', 'ẳ' => 'a', 'Ể' => 'E', 'ể' => 'e', 'Ỏ' => 'O', 'ỏ' => 'o',
            'Ổ' => 'O', 'ổ' => 'o', 'Ở' => 'O', 'ở' => 'o', 'Ủ' => 'U', 'ủ' => 'u',
            'Ử' => 'U', 'ử' => 'u', 'Ỷ' => 'Y', 'ỷ' => 'y', 'Ẫ' => 'A', 'ẫ' => 'a',
            'Ậ' => 'A', 'ậ' => 'a', 'Ắ' => 'A', 'ắ' => 'a', 'Ế' => 'E', 'ế' => 'e',
            'Ố' => 'O', 'ố' => 'o', 'Ớ' => 'O', 'ớ' => 'o', 'Ứ' => 'U', 'ứ' => 'u',
            'Ạ' => 'A', 'ạ' => 'a', 'Ậ' => 'A', 'ậ' => 'a', 'Ặ' => 'A', 'ặ' => 'a',
            'Ẹ' => 'E', 'ẹ' => 'e', 'Ệ' => 'E', 'ệ' => 'e', 'Ỉ' => 'I', 'ỉ' => 'i',
            'Ị' => 'I', 'ị' => 'i', 'Ọ' => 'O', 'ọ' => 'o', 'Ợ' => 'O', 'ợ' => 'o',
            'Ụ' => 'U', 'ụ' => 'u', 'Ỵ' => 'Y', 'ỵ' => 'y', 'Ỹ' => 'Y', 'ỹ' => 'y',
            'Ấ' => 'A', 'ấ' => 'a', 'Ắ' => 'A', 'ắ' => 'a', 'Ế' => 'E', 'ế' => 'e',
            'Ố' => 'O', 'ố' => 'o', 'Ớ' => 'O', 'ớ' => 'o', 'Ứ' => 'U', 'ứ' => 'u',
        ];

        if (
            'de_DE' === $locale || 'de_DE_formal' === $locale
            || 'de_CH' === $locale || 'de_CH_informal' === $locale
            || 'de_AT' === $locale
        ) {
            $chars['Ä'] = 'Ae';
            $chars['ä'] = 'ae';
            $chars['Ö'] = 'Oe';
            $chars['ö'] = 'oe';
            $chars['Ü'] = 'Ue';
            $chars['ü'] = 'ue';
            $chars['ß'] = 'ss';
        } elseif ('da_DK' === $locale) {
            $chars['Æ'] = 'Ae';
            $chars['æ'] = 'ae';
            $chars['Ø'] = 'Oe';
            $chars['ø'] = 'oe';
            $chars['Å'] = 'Aa';
            $chars['å'] = 'aa';
        } elseif ('ca' === $locale) {
            $chars['l·l'] = 'll';
        } elseif ('sr_RS' === $locale || 'bs_BA' === $locale) {
            $chars['Đ'] = 'DJ';
            $chars['đ'] = 'dj';
        }

        return strtr($text, $chars);
    }

    $chars = [];
    $chars['in'] = "\x80\x83\x8a\x8e\x9a\x9e"
        . "\x9f\xa2\xa5\xb5\xc0\xc1\xc2"
        . "\xc3\xc4\xc5\xc7\xc8\xc9\xca"
        . "\xcb\xcc\xcd\xce\xcf\xd1\xd2"
        . "\xd3\xd4\xd5\xd6\xd8\xd9\xda"
        . "\xdb\xdc\xdd\xe0\xe1\xe2\xe3"
        . "\xe4\xe5\xe7\xe8\xe9\xea\xeb"
        . "\xec\xed\xee\xef\xf1\xf2\xf3"
        . "\xf4\xf5\xf6\xf8\xf9\xfa\xfb"
        . "\xfc\xfd\xff";

    $chars['out'] = 'EfSZszYcYuAAAAAACEEEEIIIINOOOOOOUUUUYaaaaaaceeeeiiiinoooooouuuuyy';

    $text = strtr($text, $chars['in'], $chars['out']);

    $double_chars['in']  = ["\x8c", "\x9c", "\xc6", "\xd0", "\xde", "\xdf", "\xe6", "\xf0", "\xfe"];
    $double_chars['out'] = ['OE', 'oe', 'AE', 'DH', 'TH', 'ss', 'ae', 'dh', 'th'];
    return str_replace($double_chars['in'], $double_chars['out'], $text);
}

/**
 * Determine if a string is valid UTF-8.
 *
 * @param string $str Input string.
 *
 * @return bool
 */
function seemsUtf8(string $str): bool
{
    return mb_detect_encoding($str, 'UTF-8', true) !== false;
}
