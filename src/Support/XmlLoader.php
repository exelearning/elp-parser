<?php

/**
 * XmlLoader.php
 *
 * PHP Version 8.0
 *
 * @category Parser
 * @package  Exelearning
 * @author   INTEF <cedec@educacion.gob.es>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/exelearning/elp-parser
 */

namespace Exelearning\Support;

use Exelearning\Exception\InvalidXmlException;
use SimpleXMLElement;

/**
 * Load XML using non-networked libxml settings.
 */
class XmlLoader
{
    /**
     * Parse an XML document.
     *
     * @param string $xmlContent XML content.
     *
     * @return SimpleXMLElement
     */
    public function load(string $xmlContent): SimpleXMLElement
    {
        $previous = libxml_use_internal_errors(true);

        try {
            $xml = simplexml_load_string(
                $xmlContent,
                SimpleXMLElement::class,
                LIBXML_NONET | LIBXML_COMPACT
            );

            if ($xml === false) {
                $errors = libxml_get_errors();
                $message = isset($errors[0]) ? trim($errors[0]->message) : 'Unknown XML parsing error.';
                throw new InvalidXmlException('XML Parsing error: ' . $message);
            }

            return $xml;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }
}
