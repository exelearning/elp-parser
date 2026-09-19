<?php

/**
 * AssetReferenceExtractor.php
 *
 * PHP Version 8.0
 *
 * @category Parser
 * @package  Exelearning
 * @author   INTEF <cedec@educacion.gob.es>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/exelearning/elp-parser
 */

namespace Exelearning\Asset;

/**
 * Extract asset references from HTML and structured iDevice properties.
 *
 * @category Parser
 * @package  Exelearning
 * @author   INTEF <cedec@educacion.gob.es>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/exelearning/elp-parser
 */
class AssetReferenceExtractor
{
    private const ASSET_PATTERN = '/(?:\{\{context_path\}\}\/)?'
        . '([^\s"\'<>(),]+\.(?:png|jpe?g|gif|svg|webp|bmp|mp3|wav|ogg|m4a|mp4|webm|ogv|'
        . 'pdf|docx?|xlsx?|pptx?|odt|ods|odp|zip)(?:[?#][^\s"\'<>(),]*)?)/iu';

    /** @var array<string, string> */
    private array $archiveLookup = [];

    /** @var array<string, string|null> */
    private array $resolutionCache = [];

    /**
     * @param array<int, string> $archiveEntries Archive entry names.
     */
    public function __construct(array $archiveEntries)
    {
        foreach ($archiveEntries as $entry) {
            $normalized = $this->normalizePath($entry);
            if ($normalized !== null) {
                $this->archiveLookup[$normalized] = $entry;
            }
        }
    }

    /**
     * Extract unique referenced assets with their page/iDevice origins.
     *
     * @param array<int, array<string, mixed>> $pages Parsed pages.
     *
     * @return array<int, array<string, mixed>>
     */
    public function extract(array $pages): array
    {
        $assets = [];

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

                if (($idevice['storagePattern'] ?? '') !== 'standard-json') {
                    $this->collectStringValues($idevice['data'] ?? [], $sources);
                }

                foreach ($sources as $source) {
                    foreach ($this->extractPathsFromString($source) as $path) {
                        $assets[$path] ??= [
                            'path' => $path,
                            'type' => $this->detectAssetType($path),
                            'extension' => strtolower((string) pathinfo($path, PATHINFO_EXTENSION)),
                            'pages' => [],
                            'idevices' => [],
                            'occurrences' => 0,
                        ];

                        $pageKey = (string) (($page['id'] ?? '') ?: ($page['title'] ?? ''));
                        $ideviceKey = (string) (($idevice['id'] ?? '') ?: ($pageKey . ':' . ($idevice['type'] ?? '')));

                        $assets[$path]['pages'][$pageKey] = [
                            'id' => $page['id'] ?? '',
                            'title' => $page['title'] ?? '',
                        ];
                        $assets[$path]['idevices'][$ideviceKey] = [
                            'id' => $idevice['id'] ?? '',
                            'type' => $idevice['type'] ?? '',
                            'pageId' => $page['id'] ?? '',
                            'pageTitle' => $page['title'] ?? '',
                        ];
                        $assets[$path]['occurrences']++;
                    }
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
     * Find asset references that cannot be resolved to archive entries.
     *
     * @param array<int, array<string, mixed>> $pages Parsed pages.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findBrokenReferences(array $pages): array
    {
        $broken = [];

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

                if (($idevice['storagePattern'] ?? '') !== 'standard-json') {
                    $this->collectStringValues($idevice['data'] ?? [], $sources);
                }

                foreach ($sources as $source) {
                    foreach ($this->extractCandidatesFromString($source) as $candidate) {
                        if ($this->resolveArchivePath($candidate) !== null) {
                            continue;
                        }

                        $key = (string) ($page['id'] ?? '')
                            . '|'
                            . (string) ($idevice['id'] ?? '')
                            . '|'
                            . $candidate;

                        $broken[$key] = [
                            'reference' => $candidate,
                            'type' => $this->detectAssetType($candidate),
                            'pageId' => $page['id'] ?? '',
                            'pageTitle' => $page['title'] ?? '',
                            'ideviceId' => $idevice['id'] ?? '',
                            'ideviceType' => $idevice['type'] ?? '',
                        ];
                    }
                }
            }
        }

        return array_values($broken);
    }

    /**
     * Return the logical asset type for a path.
     *
     * @param string $path Asset path.
     *
     * @return string
     */
    public function detectAssetType(string $path): string
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
     * Extract canonical package paths from a string.
     *
     * @param string $source HTML, CSS, JSON value, or other text.
     *
     * @return array<int, string>
     */
    private function extractPathsFromString(string $source): array
    {
        $paths = [];

        foreach ($this->extractCandidatesFromString($source) as $candidate) {
            $path = $this->resolveArchivePath($candidate);
            if ($path !== null) {
                $paths[] = $path;
            }
        }

        return array_values(array_unique($paths));
    }

    /**
     * Extract package-like asset reference candidates from arbitrary text.
     *
     * @param string $source HTML, CSS, JSON value, or other text.
     *
     * @return array<int, string>
     */
    private function extractCandidatesFromString(string $source): array
    {
        if ($source === '') {
            return [];
        }

        preg_match_all(
            self::ASSET_PATTERN,
            html_entity_decode($source, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            $matches
        );

        $candidates = [];
        foreach (($matches[1] ?? []) as $candidate) {
            $candidate = trim((string) $candidate, " \t\n\r\0\x0B\"'");
            if ($candidate === '' || preg_match('#^[a-z][a-z0-9+.-]*:#i', $candidate) === 1) {
                continue;
            }

            $candidates[] = $candidate;
        }

        return array_values(array_unique($candidates));
    }

    /**
     * Recursively collect string values from decoded JSON properties.
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

    /**
     * Resolve a reference to the canonical path stored in the archive.
     *
     * @param string $candidate Raw asset reference.
     *
     * @return string|null
     */
    private function resolveArchivePath(string $candidate): ?string
    {
        if (array_key_exists($candidate, $this->resolutionCache)) {
            return $this->resolutionCache[$candidate];
        }

        $resolved = $this->resolveArchivePathUncached($candidate);
        $this->resolutionCache[$candidate] = $resolved;

        return $resolved;
    }

    /**
     * Resolve an uncached asset reference against archive entries.
     *
     * @param string $candidate Raw asset reference.
     *
     * @return string|null
     */
    private function resolveArchivePathUncached(string $candidate): ?string
    {
        $candidate = trim($candidate, " \t\n\r\0\x0B\"'");
        $candidate = str_replace('{{context_path}}/', '', $candidate);

        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $candidate) === 1) {
            return null;
        }

        $candidate = preg_split('/[?#]/', $candidate, 2)[0] ?? '';
        $candidate = rawurldecode($candidate);
        $normalized = $this->normalizePath($candidate);

        if ($normalized === null) {
            return null;
        }

        $variants = [$normalized];

        if (str_starts_with($normalized, 'resources/')) {
            $variants[] = 'content/' . $normalized;
        } elseif (!str_starts_with($normalized, 'content/')) {
            // eXeLearning 4 uses {{context_path}}/<exportPath>, while v3
            // commonly stored the full content/resources path in references.
            $variants[] = 'content/resources/' . $normalized;
            $variants[] = 'content/' . $normalized;
        }

        foreach (array_unique(array_filter($variants)) as $variant) {
            if (isset($this->archiveLookup[$variant])) {
                return $this->archiveLookup[$variant];
            }
        }

        return null;
    }

    /**
     * Normalize a package path without allowing it to move above the archive root.
     *
     * @param string $path Raw path.
     *
     * @return string|null
     */
    private function normalizePath(string $path): ?string
    {
        $path = str_replace('\\', '/', trim($path));
        $path = ltrim($path, '/');
        $segments = [];

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                if ($segments === []) {
                    return null;
                }

                array_pop($segments);
                continue;
            }

            $segments[] = $segment;
        }

        if ($segments === []) {
            return null;
        }

        return implode('/', $segments);
    }
}
