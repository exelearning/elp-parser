<?php

/**
 * ProjectDiffer.php
 *
 * PHP Version 8.0
 *
 * @category Diff
 * @package  Exelearning
 * @author   INTEF <cedec@educacion.gob.es>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/exelearning/elp-parser
 */

namespace Exelearning\Diff;

use Exelearning\ELPParser;

/**
 * Compare two parsed eXeLearning projects semantically.
 */
final class ProjectDiffer
{
    /**
     * Compare two parsed projects.
     *
     * @param ELPParser $left      Left project.
     * @param ELPParser $right     Right project.
     * @param string    $algorithm Hash algorithm for content/assets.
     *
     * @return array<string, mixed>
     */
    public function diff(
        ELPParser $left,
        ELPParser $right,
        string $algorithm = 'sha256'
    ): array {
        $metadata = $this->diffMetadata($left, $right);
        $pages = $this->diffItems(
            $this->stripNestedPages($left->getPages()),
            $this->stripNestedPages($right->getPages()),
            'page'
        );
        $blocks = $this->diffItems(
            $this->stripBlockComponents($left->getBlocks()),
            $this->stripBlockComponents($right->getBlocks()),
            'block'
        );
        $idevices = $this->diffItems(
            $left->getIdevices(),
            $right->getIdevices(),
            'idevice'
        );
        $assets = $this->diffAssets($left, $right, $algorithm);

        $leftFingerprint = $left->getContentFingerprint($algorithm);
        $rightFingerprint = $right->getContentFingerprint($algorithm);

        return [
            'changed' => $leftFingerprint !== $rightFingerprint,
            'fingerprints' => [
                'left' => $leftFingerprint,
                'right' => $rightFingerprint,
            ],
            'metadata' => $metadata,
            'pages' => $pages,
            'blocks' => $blocks,
            'idevices' => $idevices,
            'assets' => $assets,
        ];
    }

    /**
     * Compare core metadata fields.
     *
     * @param ELPParser $left  Left project.
     * @param ELPParser $right Right project.
     *
     * @return array<string, array{before:mixed,after:mixed}>
     */
    private function diffMetadata(ELPParser $left, ELPParser $right): array
    {
        $leftData = [
            'formatFamily' => $left->getFormatFamily(),
            'formatVersion' => $left->getFormatVersion(),
            'title' => $left->getTitle(),
            'description' => $left->getDescription(),
            'author' => $left->getAuthor(),
            'license' => $left->getLicense(),
            'language' => $left->getLanguage(),
            'learningResourceType' => $left->getLearningResourceType(),
        ];
        $rightData = [
            'formatFamily' => $right->getFormatFamily(),
            'formatVersion' => $right->getFormatVersion(),
            'title' => $right->getTitle(),
            'description' => $right->getDescription(),
            'author' => $right->getAuthor(),
            'license' => $right->getLicense(),
            'language' => $right->getLanguage(),
            'learningResourceType' => $right->getLearningResourceType(),
        ];

        $changes = [];

        foreach ($leftData as $key => $before) {
            $after = $rightData[$key] ?? null;

            if ($before !== $after) {
                $changes[$key] = [
                    'before' => $before,
                    'after' => $after,
                ];
            }
        }

        return $changes;
    }

    /**
     * Compare entity arrays by ID, falling back to stable positional keys.
     *
     * @param array<int, array<string,mixed>> $left   Left entities.
     * @param array<int, array<string,mixed>> $right  Right entities.
     * @param string                          $prefix Fallback key prefix.
     *
     * @return array{added:array<int,string>,removed:array<int,string>,changed:array<int,string>}
     */
    private function diffItems(
        array $left,
        array $right,
        string $prefix
    ): array {
        $leftIndex = $this->indexItems($left, $prefix);
        $rightIndex = $this->indexItems($right, $prefix);

        $added = array_values(
            array_diff(array_keys($rightIndex), array_keys($leftIndex))
        );
        $removed = array_values(
            array_diff(array_keys($leftIndex), array_keys($rightIndex))
        );
        $changed = [];

        foreach (array_intersect(array_keys($leftIndex), array_keys($rightIndex)) as $key) {
            if ($leftIndex[$key] !== $rightIndex[$key]) {
                $changed[] = $key;
            }
        }

        sort($added);
        sort($removed);
        sort($changed);

        return [
            'added' => $added,
            'removed' => $removed,
            'changed' => $changed,
        ];
    }

    /**
     * Index entity arrays by ID or positional fallback.
     *
     * @param array<int, array<string,mixed>> $items  Entities.
     * @param string                          $prefix Fallback key prefix.
     *
     * @return array<string, array<string,mixed>>
     */
    private function indexItems(array $items, string $prefix): array
    {
        $index = [];

        foreach ($items as $position => $item) {
            $id = (string) ($item['id'] ?? '');
            $key = $id !== '' ? $id : '@' . $prefix . '-' . $position;
            $index[$key] = $item;
        }

        ksort($index);

        return $index;
    }

    /**
     * Remove nested blocks/iDevices from page-level comparisons.
     *
     * @param array<int, array<string,mixed>> $pages Pages.
     *
     * @return array<int, array<string,mixed>>
     */
    private function stripNestedPages(array $pages): array
    {
        foreach ($pages as &$page) {
            unset($page['blocks'], $page['idevices']);
        }
        unset($page);

        return $pages;
    }

    /**
     * Remove nested components from block-level comparisons.
     *
     * @param array<int, array<string,mixed>> $blocks Blocks.
     *
     * @return array<int, array<string,mixed>>
     */
    private function stripBlockComponents(array $blocks): array
    {
        foreach ($blocks as &$block) {
            unset($block['components']);
        }
        unset($block);

        return $blocks;
    }

    /**
     * Compare project resource files by package path and content hash.
     *
     * @param ELPParser $left      Left project.
     * @param ELPParser $right     Right project.
     * @param string    $algorithm Hash algorithm.
     *
     * @return array{added:array<int,string>,removed:array<int,string>,modified:array<int,string>}
     */
    private function diffAssets(
        ELPParser $left,
        ELPParser $right,
        string $algorithm
    ): array {
        $leftAssets = $this->assetHashes($left, $algorithm);
        $rightAssets = $this->assetHashes($right, $algorithm);

        $added = array_values(
            array_diff(array_keys($rightAssets), array_keys($leftAssets))
        );
        $removed = array_values(
            array_diff(array_keys($leftAssets), array_keys($rightAssets))
        );
        $modified = [];

        foreach (array_intersect(array_keys($leftAssets), array_keys($rightAssets)) as $path) {
            if ($leftAssets[$path] !== $rightAssets[$path]) {
                $modified[] = $path;
            }
        }

        sort($added);
        sort($removed);
        sort($modified);

        return [
            'added' => $added,
            'removed' => $removed,
            'modified' => $modified,
        ];
    }

    /**
     * Build resource path-to-hash map.
     *
     * @param ELPParser $parser    Parsed project.
     * @param string    $algorithm Hash algorithm.
     *
     * @return array<string, string>
     */
    private function assetHashes(
        ELPParser $parser,
        string $algorithm
    ): array {
        $manifest = $parser->getPackageManifest();
        $paths = $manifest['resourceFiles'] ?? [];

        if (!is_array($paths)) {
            $paths = [];
        }

        $paths = array_values(
            array_unique(
                array_merge(
                    $paths,
                    $parser->getAssets(),
                    $parser->getOrphanAssets()
                )
            )
        );
        sort($paths);

        $hashes = [];
        foreach ($paths as $path) {
            if (!is_string($path) || $path === '') {
                continue;
            }

            $hashes[$path] = $parser->getArchiveEntryFingerprint(
                $path,
                $algorithm
            );
        }

        return $hashes;
    }
}
