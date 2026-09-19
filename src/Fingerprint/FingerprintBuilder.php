<?php

/**
 * FingerprintBuilder.php
 *
 * PHP Version 8.0
 *
 * @category Fingerprint
 * @package  Exelearning
 * @author   INTEF <cedec@educacion.gob.es>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/exelearning/elp-parser
 */

namespace Exelearning\Fingerprint;

use Exelearning\ELPParser;
use Exelearning\Exception\ElpParserException;
use JsonException;

/**
 * Build deterministic logical-content fingerprints for parsed projects.
 */
final class FingerprintBuilder
{
    /**
     * Build a normalized logical-content fingerprint.
     *
     * Packaging files and volatile project/version metadata are excluded.
     * Parsed structure and project resource bytes are included.
     *
     * @param ELPParser $parser    Parsed project.
     * @param string    $algorithm Hash algorithm.
     *
     * @return string
     */
    public function build(
        ELPParser $parser,
        string $algorithm = 'sha256'
    ): string {
        if (!in_array($algorithm, hash_algos(), true)) {
            throw new ElpParserException(
                'Unsupported hash algorithm: ' . $algorithm
            );
        }

        $resources = $parser->getOdeResources();

        foreach (
            [
                'odeId',
                'odeVersionId',
                'odeVersionName',
                'exe_version',
                'eXeVersion',
                'isDownload',
            ] as $volatileKey
        ) {
            unset($resources[$volatileKey]);
        }

        $resourceFiles = $parser->getPackageManifest()['resourceFiles'] ?? [];
        if (!is_array($resourceFiles)) {
            $resourceFiles = [];
        }

        $assetPaths = array_values(
            array_unique(
                array_merge(
                    $resourceFiles,
                    $parser->getAssets(),
                    $parser->getOrphanAssets()
                )
            )
        );
        sort($assetPaths);

        $assetFingerprints = [];
        foreach ($assetPaths as $path) {
            if (!is_string($path) || $path === '') {
                continue;
            }

            $assetFingerprints[] = [
                'path' => $path,
                'hash' => $parser->getArchiveEntryFingerprint(
                    $path,
                    $algorithm
                ),
            ];
        }

        $data = [
            'formatFamily' => $parser->getFormatFamily(),
            'formatVersion' => $parser->getFormatVersion(),
            'summary' => [
                'title' => $parser->getTitle(),
                'description' => $parser->getDescription(),
                'author' => $parser->getAuthor(),
                'license' => $parser->getLicense(),
                'language' => $parser->getLanguage(),
                'learningResourceType' => $parser->getLearningResourceType(),
            ],
            'userPreferences' => $parser->getUserPreferences(),
            'odeProperties' => $parser->getOdeProperties(),
            'odeResources' => $resources,
            'pages' => $parser->getPages(),
            'assets' => $assetFingerprints,
        ];

        $normalized = $this->normalize($data);

        try {
            $json = json_encode(
                $normalized,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_THROW_ON_ERROR
            );
        } catch (JsonException $exception) {
            throw new ElpParserException(
                'Unable to serialize normalized project content: '
                . $exception->getMessage(),
                0,
                $exception
            );
        }

        return hash($algorithm, $json);
    }

    /**
     * Recursively sort associative arrays while preserving list order.
     *
     * @param mixed $value Value to normalize.
     *
     * @return mixed
     */
    private function normalize(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if ($this->isList($value)) {
            return array_map(
                fn(mixed $item): mixed => $this->normalize($item),
                $value
            );
        }

        ksort($value);

        foreach ($value as $key => $item) {
            $value[$key] = $this->normalize($item);
        }

        return $value;
    }

    /**
     * PHP 8.0-compatible array_is_list implementation.
     *
     * @param array<mixed> $value Array to inspect.
     *
     * @return bool
     */
    private function isList(array $value): bool
    {
        $expected = 0;

        foreach (array_keys($value) as $key) {
            if ($key !== $expected) {
                return false;
            }

            $expected++;
        }

        return true;
    }
}
