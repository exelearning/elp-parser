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

use ZipArchive;
use SimpleXMLElement;
use Exception;

/**
 * ELPParser class for parsing .elp (eXeLearning) project files
 *
 * This class provides functionality to parse .elp files, which are ZIP archives
 * containing XML content for eXeLearning projects. It supports both version 2 and 3 formats.
 *
 * @category Parser
 * @package  Exelearning
 * @author   INTEF <cedec@educacion.gob.es>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/exelearning/elp-parser
 */
class ELPParser implements \JsonSerializable
{
    /**
     * Path to the .elp file
     * 
     * @var string
     */
    protected string $filePath;

    /**
     * ELP file version (2 or 3)
     * 
     * @var int
     */
    protected int $version;

    /**
     * Extracted content and metadata
     * 
     * @var array
     */
    protected array $content = [];

    /**
     * Raw extracted strings
     * 
     * @var array
     */
    protected array $strings = [];

    /**
     * Title of the ELP content
     * 
     * @var string
     */
    protected string $title = '';

    /**
     * Description of the ELP content
     * 
     * @var string
     */
    protected string $description = '';

    /**
     * Author of the ELP content
     * 
     * @var string
     */
    protected string $author = '';

    /**
     * License of the ELP content
     * 
     * @var string
     */
    protected string $license = '';

    /**
     * Language of the ELP content
     * 
     * @var string
     */
    protected string $language = '';

    /**
     * Learning resource type
     * 
     * @var string
     */
    protected string $learningResourceType = '';

    /**
     * Create a new ELPParser instance
     *
     * @param string $filePath Path to the .elp file
     * 
     * @throws Exception If file cannot be opened or is invalid
     * @return void
     */
    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
        $this->parse();
    }

    /**
     * Static method to create an ELPParser from a file path
     *
     * @param string $filePath Path to the .elp file
     * 
     * @throws Exception If file cannot be opened or is invalid
     * @return self
     */
    public static function fromFile(string $filePath): self
    {
        return new self($filePath);
    }

    /**
     * Detect the ELP file version and parse its contents
     *
     * @throws Exception If file parsing fails
     * @return void
     */
    protected function parse(): void
    {
        $zip = new ZipArchive();
        
        if (!file_exists($this->filePath)) {
            throw new Exception('File does not exist.');
        }

        // Check MIME type
        $mimeType = mime_content_type($this->filePath);
        if ($mimeType !== 'application/zip') {
            throw new Exception('The file is not a valid ZIP file.');
        }

        $zip = new ZipArchive();
        if ($zip->open($this->filePath) !== true) {
            throw new Exception('Unable to open the ZIP file.');
        }

        // Detect version
        if ($zip->locateName('content.xml') !== false && $zip->locateName('index.html') !== false) {
            $this->version = 3;
            $contentFile = 'content.xml';
        } elseif ($zip->locateName('contentv3.xml') !== false) {
            $this->version = 2;
            $contentFile = 'contentv3.xml';
        } else {
            $zip->close();
            throw new Exception("Invalid ELP file: No content XML found.");
        }

        // Extract content
        $xmlContent = $zip->getFromName($contentFile);
        $zip->close();

        if ($xmlContent === false) {
            throw new Exception("Failed to read XML content");
        }

        $this->parseXML($xmlContent);
    }


    /**
     * Parse the XML content and extract relevant information
     *
     * @param string $xmlContent XML content as a string
     * 
     * @throws Exception If XML parsing fails
     * @return void
     */
    protected function parseXML(string $xmlContent): void
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlContent);

        if ($xml === false) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            throw new Exception("XML Parsing error: " . $errors[0]->message);
        }

        if ($this->version === 2) {
            $this->extractVersion2Metadata($xml);
        } else if ($this->version === 3) {
            $this->extractVersion3Metadata($xml);
        }

        // Extract all strings
        $this->extractStrings($xml);
    }

    /**
     * Extract strings from the XML document
     *
     * @param SimpleXMLElement $xml XML document
     * 
     * @return void
     */
    protected function extractStrings(SimpleXMLElement $xml): void
    {
        // Customize this method to extract specific strings based on your needs
        $this->strings = $this->recursiveStringExtraction($xml);
    }

    /**
     * Recursively extract all text strings from XML
     *
     * @param SimpleXMLElement $element XML element to extract from
     * 
     * @return array Extracted strings
     */
    protected function recursiveStringExtraction(SimpleXMLElement $element): array
    {
        $strings = [];

        // Convert SimpleXMLElement to array to handle complex structures
        $elementArray = (array)$element;

        foreach ($elementArray as $key => $value) {
            if (is_string($value) && !empty(trim($value))) {
                $strings[] = trim($value);
            } elseif ($value instanceof SimpleXMLElement) {
                $strings = array_merge($strings, $this->recursiveStringExtraction($value));
            } elseif (is_array($value)) {
                foreach ($value as $subValue) {
                    if ($subValue instanceof SimpleXMLElement) {
                        $strings = array_merge($strings, $this->recursiveStringExtraction($subValue));
                    }
                }
            }
        }

        return $strings;
    }

    /**
     * Get the detected ELP file version
     *
     * @return int ELP file version (2 or 3)
     */
    public function getVersion(): int
    {
        return $this->version;
    }

    /**
     * Get all extracted strings
     *
     * @return array List of extracted strings
     */
    public function getStrings(): array
    {
        return $this->strings;
    }

    /**
     * Convert parser data to an array
     *
     * @return array Parsed ELP file data
     */
    /**
     * Extract metadata from version 3 XML format
     *
     * @param SimpleXMLElement $xml XML document
     * 
     * @return void
     */
    protected function extractVersion3Metadata(SimpleXMLElement $xml): void
    {
        if (isset($xml->odeProperties)) {
            foreach ($xml->odeProperties->odeProperty as $property) {
                $key = (string)$property->key;
                $value = (string)$property->value;

                switch ($key) {
                case 'pp_title':
                    $this->title = $value;
                    break;
                case 'pp_description':
                    $this->description = $value;
                    break;
                case 'pp_author':
                    $this->author = $value;
                    break;
                case 'license':
                    $this->license = $value;
                    break;
                case 'lom_general_language':
                    $this->language = $value;
                    break;
                case 'pp_learningResourceType':
                    $this->learningResourceType = $value;
                    break;
                }
            }
        }
    }

    /**
     * Get the title
     *
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Get the description
     *
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Get the author
     *
     * @return string
     */
    public function getAuthor(): string
    {
        return $this->author;
    }

    /**
     * Get the license
     *
     * @return string
     */
    public function getLicense(): string
    {
        return $this->license;
    }

    /**
     * Get the language
     *
     * @return string
     */
    public function getLanguage(): string
    {
        return $this->language;
    }

    /**
     * Get the learning resource type
     *
     * @return string
     */
    public function getLearningResourceType(): string
    {
        return $this->learningResourceType;
    }

    /**
     * Extract metadata from version 2 XML format
     *
     * @param SimpleXMLElement $xml XML document
     * 
     * @return void
     */
    protected function extractVersion2Metadata(SimpleXMLElement $xml): void
    {
        if (!isset($xml->dictionary)) {
            return;
        }

        $metadata = [];
        $currentKey = null;

        foreach ($xml->dictionary->children() as $element) {
            $elementName = $element->getName();

            if ($elementName === 'string') {
                $role = (string)$element['role'];
                $value = (string)$element['value'];

                if ($role === 'key') {
                    $currentKey = $value;
                }
            } elseif ($currentKey !== null) {
                // Extract the value based on the type of element
                switch ($elementName) {
                case 'unicode':
                    $metadata[$currentKey] = (string)$element['value'];
                    break;
                case 'bool':
                    $metadata[$currentKey] = ((string)$element['value']) === '1';
                    break;
                case 'int':
                    $metadata[$currentKey] = (int)$element['value'];
                    break;
                case 'list':
                    // Handle lists if necessary
                    $listValues = [];
                    foreach ($element->children() as $listItem) {
                        if ($listItem->getName() === 'unicode') {
                            $listValues[] = (string)$listItem['value'];
                        }
                        // Add handling for other types of elements within the list if necessary
                    }
                    $metadata[$currentKey] = $listValues;
                    break;
                case 'dictionary':
                    // Handle nested dictionaries if necessary
                    // This may require a recursive function
                    // For simplicity, it can be omitted or implemented as needed
                    break;
                    // Add other cases as needed
                default:
                    // Handle unknown types or ignore them
                    break;
                }

                // Reset the current key after assigning the value
                $currentKey = null;
            }
        }

        // Map the metadata to the corresponding properties
        $this->title = $metadata['_title'] ?? '';
        $this->description = $metadata['_description'] ?? '';
        $this->author = $metadata['_author'] ?? '';
        $this->license = $metadata['license'] ?? '';
        $this->language = $metadata['_lang'] ?? '';
        $this->learningResourceType = $metadata['_learningResourceType'] ?? '';

    }


    /**
     * Serialization method
     *
     * @return array Data
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
     * JSON serialization method
     *
     * @return array Data to be JSON serialized
     */
    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    /**
     * Export parsed data as JSON string or file
     *
     * If a destination path is provided, the JSON string will be written to the
     * given file. The method returns the JSON representation in any case.
     *
     * @param string|null $destinationPath Optional path to save the JSON file
     *
     * @throws Exception If the file cannot be written
     * @return string    JSON representation of the parsed ELP data
     */
    public function exportJson(?string $destinationPath = null): string
    {
        $json = json_encode($this, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            throw new Exception('Failed to encode JSON: ' . json_last_error_msg());
        }

        if ($destinationPath !== null) {
            if (file_put_contents($destinationPath, $json) === false) {
                throw new Exception('Unable to write JSON file.');
            }
        }

        return $json;
    }

    /**
     * Get detailed metadata and content structure as an array
     *
     * This method parses the underlying XML to build a rich metadata
     * representation including package information, Dublin Core data,
     * LOM and LOM-ES schemas as well as a simplified page tree.
     *
     * @throws Exception If the XML content cannot be parsed
     * @return array Metadata and content information
     */
    public function getMetadata(): array
    {
        $zip = new ZipArchive();
        if ($zip->open($this->filePath) !== true) {
            throw new Exception('Unable to open the ZIP file.');
        }

        $contentFile = $this->version === 2 ? 'contentv3.xml' : 'content.xml';
        $xmlContent = $zip->getFromName($contentFile);
        $zip->close();

        if ($xmlContent === false) {
            throw new Exception('Failed to read XML content.');
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlContent);
        if ($xml === false) {
            throw new Exception('XML Parsing error');
        }

        $data = $this->parseElement($xml->dictionary);

        $meta = [
            [
                'schema' => 'Package',
                'content' => [
                    'title' => $data['_title'] ?? '',
                    'author' => $data['_author'] ?? '',
                    'language' => $data['_lang'] ?? '',
                    'description' => $data['_description'] ?? '',
                    'license' => $data['license'] ?? '',
                    'classification' => '',
                ],
            ],
        ];

        if (isset($data['dublinCore'])) {
            $dc = $data['dublinCore'];
            $meta[] = [
                'schema' => 'Dublin core',
                'content' => [
                    'title' => $dc['title'] ?? '',
                    'author' => $dc['creator'] ?? '',
                    'language' => $dc['language'] ?? '',
                    'description' => $dc['description'] ?? '',
                    'license' => [ 'rights' => $dc['rights'] ?? '' ],
                    'classification' => [ 'source' => $dc['source'] ?? '', 'taxon_path' => [] ],
                ],
            ];
        }

        if (isset($data['lom'])) {
            $lom = $data['lom'];
            $meta[] = [
                'schema' => 'LOM v1.0',
                'content' => [
                    'title' => $lom['general']['title']['string'] ?? [],
                    'author' => $lom['lifeCycle']['contribute']['entity'] ?? [],
                    'language' => $lom['general']['language'] ?? [],
                    'description' => $lom['general']['description'] ?? [],
                    'rights' => $lom['rights'] ?? [],
                    'classification' => $lom['classification'] ?? [],
                ],
            ];
        }

        if (isset($data['lomEs'])) {
            $lomEs = $data['lomEs'];
            $meta[] = [
                'schema' => 'LOM-ES v1.0',
                'content' => [
                    'title' => $lomEs['general']['title']['string'] ?? [],
                    'author' => $lomEs['lifeCycle']['contribute']['entity']['name'] ?? ($lomEs['lifeCycle']['contribute']['entity'] ?? ''),
                    'language' => $lomEs['general']['language'] ?? [],
                    'description' => $lomEs['general']['description'] ?? [],
                    'rights' => $lomEs['rights'] ?? [],
                    'classification' => $lomEs['classification'] ?? [],
                ],
            ];
        }

        $pages = [];
        if (isset($data['_nodeIdDict']['0'])) {
            $this->collectPages($data['_nodeIdDict']['0'], 0, $pages);
        }

        return [
            'metadata' => $meta,
            'content' => [
                'file' => basename($this->filePath),
                'pages' => $pages,
            ],
        ];
    }

    /**
     * Recursively parse a dictionary structure
     *
     * @param SimpleXMLElement $element XML element
     *
     * @return mixed Parsed data
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
                $cname = $child->getName();
                if (($cname === 'string' || $cname === 'unicode') && (string) $child['role'] === 'key') {
                    $key = (string) $child['value'];
                } elseif ($key !== null) {
                    $dict[$key] = $this->parseElement($child);
                    $key = null;
                }
            }
            return $dict;
        case 'instance':
            return $this->parseElement($element->dictionary);
        case 'none':
            return null;
        case 'reference':
            return ['ref' => (string) $element['key']];
        default:
            return null;
        }
    }

    /**
     * Collect page data recursively
     *
     * @param array $node  Node information
     * @param int   $level Current depth level
     * @param array $pages Accumulated pages
     *
     * @return void
     */
    protected function collectPages(array $node, int $level, array &$pages): void
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
                            $html = $field['content_w_resourcePaths'];
                            break;
                        }
                    }
                }
                $idevices[] = [
                    'id' => $idevice['_id'] ?? '',
                    'type' => $idevice['_iDeviceDir'] ?? ($idevice['class_'] ?? ''),
                    'title' => $idevice['_title'] ?? '',
                    'text' => trim(strip_tags($html)),
                    'html_code' => $html,
                ];
            }
        }

        $pages[] = [
            'filename' => $filename,
            'pagename' => $title,
            'level' => $level,
            'idevices' => $idevices,
        ];

        if (isset($node['children']) && is_array($node['children'])) {
            foreach ($node['children'] as $child) {
                if (is_array($child)) {
                    $this->collectPages($child, $level + 1, $pages);
                }
            }
        }
    }

    /**
     * Create a filename-friendly slug from a string
     *
     * @param string $text Input text
     *
     * @return string Slug
     */
    protected function slug(string $text): string
    {
        $slug = removeAccents($text);
        $slug = strtolower($slug);
        $slug = preg_replace('/[^a-z0-9]+/', '_', $slug);
        return trim($slug, '_');
    }

    /**
     * Extract contents of an ELP file to a specified directory
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
            throw new Exception("Unable to open ELP file for extraction");
        }

        if (!file_exists($destinationPath)) {
            mkdir($destinationPath, 0755, true);
        }

        $zip->extractTo($destinationPath);
        $zip->close();
    }
}

/**
 * Remove accents from a string using WordPress\' implementation.
 *
 * This function is copied from WordPress 6.8.1 and retains its original
 * copyright notice.
 *
 * @param  string $text   Text that might have accent characters.
 * @param  string $locale Optional. The locale to use for accent removal.
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

        if ('de_DE' === $locale || 'de_DE_formal' === $locale 
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
 * @param  string $str Input string.
 *
 * @return bool True if the string is valid UTF-8.
 */
function seemsUtf8(string $str): bool
{
    return mb_detect_encoding($str, 'UTF-8', true) !== false;
}
