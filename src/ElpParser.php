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
        print_r($mimeType);
        die();
        if ($mimeType !== 'application/zip') {
            throw new Exception('The file is not a valid ZIP file.');
        }

        $zip = new ZipArchive();
        if ($zip->open($this->filePath) !== true) {
            throw new Exception('Unable to open the ZIP file.');
        }

        // Detect version
        if ($zip->locateName('content.xml') !== false) {
            $this->version = 2;
            $contentFile = 'content.xml';
        } elseif ($zip->locateName('contentv3.xml') !== false) {
            $this->version = 3;
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
     * Extract metadata from version 2 XML format
     *
     * @param SimpleXMLElement $xml XML document
     * 
     * @return void
     */
    protected function extractVersion2Metadata(SimpleXMLElement $xml): void
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
     * Get the learning resource type
     *
     * @return string
     */
    public function getLearningResourceType(): string
    {
        return $this->learningResourceType;
    }

    /**
     * Extract metadata from version 3 XML format
     *
     * @param SimpleXMLElement $xml XML document
     * 
     * @return void
     */
    protected function extractVersion3Metadata(SimpleXMLElement $xml): void
    {
        if (!isset($xml->dictionary)) {
            return;
        }

        $metadata = [];
        $currentKey = null;

        foreach ($xml->dictionary->children() as $element) {
            if ($element->getName() === 'string') {
                $role = (string)$element['role'];
                $value = (string)$element['value'];

                if ($role === 'key') {
                    $currentKey = $value;
                } elseif ($currentKey !== null) {
                    $metadata[$currentKey] = $value;
                    $currentKey = null;
                }
            }
        }

        // Map the metadata to properties
        $this->title = $metadata['title'] ?? '';
        $this->description = $metadata['description'] ?? '';
        $this->author = $metadata['author'] ?? '';
        $this->license = $metadata['license'] ?? '';
        $this->learningResourceType = $metadata['learningResourceType'] ?? '';
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
