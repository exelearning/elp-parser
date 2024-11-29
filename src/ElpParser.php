<?php
/**
 * ElpParser.php
 *
 * PHP Version 8.1
 *
 * @category Library
 * @package  ELPParser
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
 */
class ELPParser implements \JsonSerializable
{
    /**
     * @var string Path to the .elp file 
     */
    protected string $filePath;

    /**
     * @var int ELP file version (2 or 3) 
     */
    protected int $version;

    /**
     * @var array Extracted content and metadata 
     */
    protected array $content = [];

    /**
     * @var array Raw extracted strings 
     */
    protected array $strings = [];

    /**
     * Create a new ELPParser instance
     *
     * @param  string $filePath Path to the .elp file
     * @throws Exception If file cannot be opened or is invalid
     */
    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
        $this->parse();
    }

    /**
     * Static method to create an ELPParser from a file path
     *
     * @param  string $filePath Path to the .elp file
     * @return self
     * @throws Exception
     */
    public static function fromFile(string $filePath): self
    {
        return new self($filePath);
    }

    /**
     * Detect the ELP file version and parse its contents
     *
     * @throws Exception If file parsing fails
     */
    protected function parse(): void
    {
        $zip = new ZipArchive();
        
        if ($zip->open($this->filePath) !== true) {
            throw new Exception("Unable to open ELP file: {$this->filePath}");
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
            throw new Exception("Invalid ELP file: No content XML found");
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
     * @param  string $xmlContent XML content as a string
     * @throws Exception If XML parsing fails
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

        // Extract generic metadata and strings
        $this->extractStrings($xml);
    }

    /**
     * Extract strings from the XML document
     *
     * @param SimpleXMLElement $xml XML document
     */
    protected function extractStrings(SimpleXMLElement $xml): void
    {
        // Customize this method to extract specific strings based on your needs
        $this->strings = $this->recursiveStringExtraction($xml);
    }

    /**
     * Recursively extract all text strings from XML
     *
     * @param  SimpleXMLElement $element XML element to extract from
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
    public function toArray(): array
    {
        return [
            'version' => $this->version,
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
     * @param  string $destinationPath Directory to extract contents to
     * @throws Exception If extraction fails
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
