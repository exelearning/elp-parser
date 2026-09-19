<?php

/**
 * IdeviceStateParser.php
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

/**
 * Normalize the four iDevice state-storage patterns used by modern ELPX files.
 */
class IdeviceStateParser
{
    public const PATTERN_STANDARD_JSON = 'standard-json';
    public const PATTERN_DATA_GAME = 'data-game';
    public const PATTERN_EMBEDDED_JSON = 'embedded-json';
    public const PATTERN_HTML_ONLY = 'html-only';

    /**
     * Parse normalized iDevice state.
     *
     * @param string $html              Rendered iDevice HTML.
     * @param string $jsonPropertiesRaw Raw jsonProperties content.
     *
     * @return array{storagePattern:string,data:mixed,decodeError:?string}
     */
    public function parse(string $html, string $jsonPropertiesRaw): array
    {
        $dataGame = $this->extractDataGame($html);
        if ($dataGame !== null) {
            return $dataGame;
        }

        $embeddedJson = $this->extractEmbeddedJson($html);
        if ($embeddedJson !== null) {
            return $embeddedJson;
        }

        if (trim($jsonPropertiesRaw) !== '') {
            return $this->decodeJson(
                $jsonPropertiesRaw,
                self::PATTERN_STANDARD_JSON
            );
        }

        return [
            'storagePattern' => self::PATTERN_HTML_ONLY,
            'data' => [],
            'decodeError' => null,
        ];
    }

    /**
     * Extract URI-encoded DataGame state from htmlView.
     *
     * @param string $html Rendered HTML.
     *
     * @return array{storagePattern:string,data:mixed,decodeError:?string}|null
     */
    private function extractDataGame(string $html): ?array
    {
        if (
            preg_match(
                '/<[^>]*class=("|\')[^"\']*DataGame[^"\']*\1[^>]*>(.*?)<\/[^>]+>/is',
                $html,
                $matches
            ) !== 1
        ) {
            return null;
        }

        $payload = trim(
            html_entity_decode(
                strip_tags((string) ($matches[2] ?? '')),
                ENT_QUOTES | ENT_HTML5,
                'UTF-8'
            )
        );

        return $this->decodeJson(
            rawurldecode($payload),
            self::PATTERN_DATA_GAME
        );
    }

    /**
     * Extract JSON embedded in the interactive-video HTML payload.
     *
     * @param string $html Rendered HTML.
     *
     * @return array{storagePattern:string,data:mixed,decodeError:?string}|null
     */
    private function extractEmbeddedJson(string $html): ?array
    {
        if (
            preg_match(
                '/<(?:script|div)\b[^>]*id=("|\')exe-interactive-video-contents\1[^>]*>'
                    . '(.*?)<\/(?:script|div)>/is',
                $html,
                $matches
            ) !== 1
        ) {
            return null;
        }

        $payload = trim(
            html_entity_decode(
                (string) ($matches[2] ?? ''),
                ENT_QUOTES | ENT_HTML5,
                'UTF-8'
            )
        );

        return $this->decodeJson(
            $payload,
            self::PATTERN_EMBEDDED_JSON
        );
    }

    /**
     * Decode JSON while preserving a stable pattern and error description.
     *
     * @param string $json    JSON payload.
     * @param string $pattern Storage-pattern identifier.
     *
     * @return array{storagePattern:string,data:mixed,decodeError:?string}
     */
    private function decodeJson(string $json, string $pattern): array
    {
        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'storagePattern' => $pattern,
                'data' => [],
                'decodeError' => json_last_error_msg(),
            ];
        }

        return [
            'storagePattern' => $pattern,
            'data' => $data,
            'decodeError' => null,
        ];
    }
}
