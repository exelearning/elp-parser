<?php

/**
 * HtmlText.php
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

/**
 * Convert stored HTML fragments to normalized plain text.
 */
class HtmlText
{
    /**
     * Convert HTML to plain text.
     *
     * @param string $html HTML fragment.
     *
     * @return string
     */
    public function convert(string $html): string
    {
        if ($html === '') {
            return '';
        }

        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text);

        return trim((string) $text);
    }
}
