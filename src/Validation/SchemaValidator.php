<?php

/**
 * SchemaValidator.php
 *
 * PHP Version 8.0
 *
 * @category Validation
 * @package  Exelearning
 * @author   INTEF <cedec@educacion.gob.es>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/exelearning/elp-parser
 */

namespace Exelearning\Validation;

use DOMDocument;
use LibXMLError;

/**
 * Validate project XML against a caller-supplied trusted XSD or DTD.
 */
class SchemaValidator
{
    public const TYPE_XSD = 'xsd';
    public const TYPE_DTD = 'dtd';

    /**
     * Validate XML against a trusted local schema.
     *
     * @param string $xmlContent XML content.
     * @param string $schemaPath Trusted local XSD or DTD path.
     * @param string $type       Schema type.
     *
     * @return array{valid:bool,errors:array<int,array<string,mixed>>}
     */
    public function validate(
        string $xmlContent,
        string $schemaPath,
        string $type = self::TYPE_XSD
    ): array {
        if (!class_exists(DOMDocument::class)) {
            return $this->failure(
                'dom_extension_missing',
                'The DOM extension is required for schema validation.'
            );
        }

        if (!in_array($type, [self::TYPE_XSD, self::TYPE_DTD], true)) {
            return $this->failure(
                'unsupported_schema_type',
                'Schema type must be xsd or dtd.'
            );
        }

        $realSchemaPath = realpath($schemaPath);
        if ($realSchemaPath === false || !is_file($realSchemaPath)) {
            return $this->failure(
                'schema_not_found',
                'The trusted schema file does not exist.'
            );
        }

        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            if ($type === self::TYPE_XSD) {
                return $this->validateXsd($xmlContent, $realSchemaPath);
            }

            return $this->validateDtd($xmlContent, $realSchemaPath);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /**
     * Validate XML against XSD.
     *
     * @param string $xmlContent XML content.
     * @param string $schemaPath Trusted XSD path.
     *
     * @return array{valid:bool,errors:array<int,array<string,mixed>>}
     */
    private function validateXsd(string $xmlContent, string $schemaPath): array
    {
        $document = new DOMDocument();

        if (!$document->loadXML($xmlContent, LIBXML_NONET)) {
            return [
                'valid' => false,
                'errors' => $this->collectErrors(),
            ];
        }

        $valid = $document->schemaValidate($schemaPath);

        return [
            'valid' => $valid,
            'errors' => $this->collectErrors(),
        ];
    }

    /**
     * Validate XML against DTD.
     *
     * The package-supplied DOCTYPE is removed and replaced with the trusted
     * local DTD path supplied by the caller.
     *
     * @param string $xmlContent XML content.
     * @param string $schemaPath Trusted DTD path.
     *
     * @return array{valid:bool,errors:array<int,array<string,mixed>>}
     */
    private function validateDtd(string $xmlContent, string $schemaPath): array
    {
        $probe = new DOMDocument();

        if (!$probe->loadXML($xmlContent, LIBXML_NONET)) {
            return [
                'valid' => false,
                'errors' => $this->collectErrors(),
            ];
        }

        $rootName = $probe->documentElement !== null
            ? $probe->documentElement->nodeName
            : '';

        if ($rootName === '') {
            return $this->failure(
                'missing_root_element',
                'Unable to determine the XML root element.'
            );
        }

        $withoutDoctype = preg_replace(
            '/<!DOCTYPE[^>]*(?:\[[\s\S]*?\]\s*)?>/i',
            '',
            $xmlContent
        );

        if ($withoutDoctype === null) {
            return $this->failure(
                'doctype_rewrite_failed',
                'Unable to prepare XML for trusted DTD validation.'
            );
        }

        $schemaUri = $this->localFileUri($schemaPath);
        $doctype = '<!DOCTYPE ' . $rootName . ' SYSTEM "' . $schemaUri . '">';
        $withDoctype = $this->insertDoctype($withoutDoctype, $doctype);

        libxml_clear_errors();

        $document = new DOMDocument();
        $loaded = $document->loadXML(
            $withDoctype,
            LIBXML_NONET | LIBXML_DTDLOAD | LIBXML_DTDVALID
        );

        $valid = $loaded && $document->validate();

        return [
            'valid' => $valid,
            'errors' => $this->collectErrors(),
        ];
    }

    /**
     * Insert a trusted DOCTYPE after the XML declaration when present.
     *
     * @param string $xml     XML content without a DOCTYPE.
     * @param string $doctype Trusted DOCTYPE declaration.
     *
     * @return string
     */
    private function insertDoctype(string $xml, string $doctype): string
    {
        if (preg_match('/^\s*<\?xml[^>]*\?>/i', $xml, $matches) === 1) {
            $declaration = $matches[0];

            return $declaration
                . PHP_EOL
                . $doctype
                . substr($xml, strlen($declaration));
        }

        return $doctype . PHP_EOL . $xml;
    }

    /**
     * Convert an absolute local path to a file URI.
     *
     * @param string $path Absolute local path.
     *
     * @return string
     */
    private function localFileUri(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $path = str_replace(' ', '%20', $path);

        return str_starts_with($path, '/')
            ? 'file://' . $path
            : 'file:///' . $path;
    }

    /**
     * Convert libxml errors to stable arrays.
     *
     * @return array<int, array<string, mixed>>
     */
    private function collectErrors(): array
    {
        return array_map(
            static fn(LibXMLError $error): array => [
                'code' => $error->code,
                'level' => $error->level,
                'line' => $error->line,
                'column' => $error->column,
                'message' => trim($error->message),
            ],
            libxml_get_errors()
        );
    }

    /**
     * Build a single stable validation failure.
     *
     * @param string $code    Error code.
     * @param string $message Error message.
     *
     * @return array{valid:bool,errors:array<int,array<string,mixed>>}
     */
    private function failure(string $code, string $message): array
    {
        return [
            'valid' => false,
            'errors' => [
                [
                    'code' => $code,
                    'message' => $message,
                ],
            ],
        ];
    }
}
