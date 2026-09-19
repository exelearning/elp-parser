<?php

/**
 * InternalReferenceExtractor.php
 *
 * PHP Version 8.0
 *
 * @category Parser
 * @package  Exelearning
 * @author   INTEF <cedec@educacion.gob.es>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/exelearning/elp-parser
 */

namespace Exelearning\Reference;

/**
 * Extract internal exe-node page references from normalized project content.
 */
final class InternalReferenceExtractor
{
    private const INTERNAL_LINK_PATTERN = '/exe-node:([A-Za-z0-9_-]+)/';

    /**
     * Extract internal page references with their page/iDevice origins.
     *
     * @param array<int, array<string, mixed>> $pages Parsed pages.
     *
     * @return array<int, array<string, mixed>>
     */
    public function extract(array $pages): array
    {
        $links = [];

        foreach ($pages as $page) {
            foreach (($page['idevices'] ?? []) as $idevice) {
                if (!is_array($idevice)) {
                    continue;
                }

                $sources = [];
                $html = (string) ($idevice['html'] ?? '');
                if ($html !== '') {
                    $sources[] = $html;
                }

                $this->collectStringValues($idevice['jsonProperties'] ?? [], $sources);
                $this->collectStringValues($idevice['data'] ?? [], $sources);

                foreach ($sources as $source) {
                    foreach ($this->extractTargets($source) as $targetPageId) {
                        $key = (string) ($page['id'] ?? '')
                            . '|'
                            . (string) ($idevice['id'] ?? '')
                            . '|'
                            . $targetPageId;

                        $links[$key] ??= [
                            'targetPageId' => $targetPageId,
                            'pageId' => $page['id'] ?? '',
                            'pageTitle' => $page['title'] ?? '',
                            'ideviceId' => $idevice['id'] ?? '',
                            'ideviceType' => $idevice['type'] ?? '',
                            'occurrences' => 0,
                        ];

                        $links[$key]['occurrences']++;
                    }
                }
            }
        }

        return array_values($links);
    }

    /**
     * Extract unique target page IDs from text.
     *
     * @param string $source Arbitrary HTML/JSON text.
     *
     * @return array<int, string>
     */
    private function extractTargets(string $source): array
    {
        if ($source === '') {
            return [];
        }

        $decoded = html_entity_decode(
            rawurldecode($source),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );

        preg_match_all(self::INTERNAL_LINK_PATTERN, $decoded, $matches);

        return array_values(
            array_unique(
                array_filter(
                    array_map('strval', $matches[1] ?? [])
                )
            )
        );
    }

    /**
     * Recursively collect string values from structured state.
     *
     * @param mixed              $value   Value to inspect.
     * @param array<int, string> $strings Collected strings.
     *
     * @return void
     */
    private function collectStringValues(mixed $value, array &$strings): void
    {
        if (is_string($value)) {
            $strings[] = $value;
            return;
        }

        if (!is_array($value)) {
            return;
        }

        foreach ($value as $nested) {
            $this->collectStringValues($nested, $strings);
        }
    }
}
