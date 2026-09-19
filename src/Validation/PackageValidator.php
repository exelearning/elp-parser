<?php

/**
 * PackageValidator.php
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

use Exelearning\ELPParser;

/**
 * Validate parsed project structure without making normal parsing strict.
 */
class PackageValidator
{
    /**
     * Validate a parsed project.
     *
     * @param ELPParser $parser Parsed project.
     *
     * @return array{valid:bool,errors:array<int,array<string,mixed>>,warnings:array<int,array<string,mixed>>}
     */
    public function validate(ELPParser $parser): array
    {
        $errors = [];
        $warnings = [];

        foreach ($parser->getBrokenReferences() as $reference) {
            $errors[] = [
                'code' => 'missing_asset',
                'message' => 'Referenced asset does not exist in the package.',
                'context' => $reference,
            ];
        }

        $this->validateIdentifiers($parser, $errors, $warnings);
        $this->validatePageHierarchy($parser, $errors, $warnings);
        $this->validateOrdersAndRelationships($parser, $errors, $warnings);
        $this->validatePackageBaseline($parser, $warnings);

        return [
            'valid' => $errors === [],
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Validate identifiers and duplicate IDs.
     *
     * @param ELPParser                       $parser   Parsed project.
     * @param array<int, array<string,mixed>> $errors   Validation errors.
     * @param array<int, array<string,mixed>> $warnings Validation warnings.
     *
     * @return void
     */
    private function validateIdentifiers(
        ELPParser $parser,
        array &$errors,
        array &$warnings
    ): void {
        $this->findDuplicateIds($parser->getPages(), 'page', $errors);
        $this->findDuplicateIds($parser->getBlocks(), 'block', $errors);
        $this->findDuplicateIds($parser->getIdevices(), 'idevice', $errors);

        foreach ([
            'odeId' => $parser->getProjectId(),
            'odeVersionId' => $parser->getProjectVersionId(),
        ] as $label => $identifier) {
            if ($identifier === null) {
                continue;
            }

            if (preg_match('/^[0-9]{14}[A-Z0-9]{6}$/', $identifier) !== 1) {
                $warnings[] = [
                    'code' => 'noncanonical_project_id',
                    'message' => $label . ' does not match the canonical ODE identifier format.',
                    'context' => ['field' => $label, 'value' => $identifier],
                ];
            }
        }
    }

    /**
     * Find duplicate non-empty IDs.
     *
     * @param array<int, array<string,mixed>> $items  Items to inspect.
     * @param string                          $type   Item type.
     * @param array<int, array<string,mixed>> $errors Validation errors.
     *
     * @return void
     */
    private function findDuplicateIds(array $items, string $type, array &$errors): void
    {
        $seen = [];

        foreach ($items as $item) {
            $id = (string) ($item['id'] ?? '');
            if ($id === '') {
                continue;
            }

            if (isset($seen[$id])) {
                $errors[] = [
                    'code' => 'duplicate_' . $type . '_id',
                    'message' => 'Duplicate ' . $type . ' identifier.',
                    'context' => ['id' => $id],
                ];
                continue;
            }

            $seen[$id] = true;
        }
    }

    /**
     * Validate page-parent relationships and cycles.
     *
     * @param ELPParser                       $parser Parsed project.
     * @param array<int, array<string,mixed>> $errors Validation errors.
     * @param array<int, array<string,mixed>> $warnings Validation warnings.
     *
     * @return void
     */
    private function validatePageHierarchy(
        ELPParser $parser,
        array &$errors,
        array &$warnings
    ): void {
        $pages = $parser->getPages();
        $parents = [];

        foreach ($pages as $page) {
            $id = (string) ($page['id'] ?? '');
            if ($id === '') {
                $warnings[] = [
                    'code' => 'missing_page_id',
                    'message' => 'A page has no identifier.',
                    'context' => ['title' => $page['title'] ?? ''],
                ];
                continue;
            }

            $parents[$id] = (string) ($page['parentId'] ?? '');
        }

        foreach ($parents as $id => $parentId) {
            if ($parentId !== '' && !array_key_exists($parentId, $parents)) {
                $errors[] = [
                    'code' => 'missing_parent_page',
                    'message' => 'Page references a parent page that does not exist.',
                    'context' => ['pageId' => $id, 'parentId' => $parentId],
                ];
            }

            $visited = [];
            $cursor = $id;

            while (isset($parents[$cursor]) && $parents[$cursor] !== '') {
                if (isset($visited[$cursor])) {
                    $errors[] = [
                        'code' => 'page_hierarchy_cycle',
                        'message' => 'Page hierarchy contains a cycle.',
                        'context' => ['pageId' => $id, 'cycleAt' => $cursor],
                    ];
                    break;
                }

                $visited[$cursor] = true;
                $cursor = $parents[$cursor];
            }
        }
    }

    /**
     * Validate ordering and cross-reference consistency.
     *
     * @param ELPParser                       $parser Parsed project.
     * @param array<int, array<string,mixed>> $errors Validation errors.
     * @param array<int, array<string,mixed>> $warnings Validation warnings.
     *
     * @return void
     */
    private function validateOrdersAndRelationships(
        ELPParser $parser,
        array &$errors,
        array &$warnings
    ): void {
        $pageOrders = [];

        foreach ($parser->getPages() as $page) {
            $parentId = (string) ($page['parentId'] ?? '');
            $order = $page['order'] ?? null;
            if (is_int($order)) {
                $key = $parentId . ':' . $order;
                if (isset($pageOrders[$key])) {
                    $warnings[] = [
                        'code' => 'duplicate_page_order',
                        'message' => 'Sibling pages share the same order value.',
                        'context' => ['parentId' => $parentId, 'order' => $order],
                    ];
                }
                $pageOrders[$key] = true;
            }

            $pageId = (string) ($page['id'] ?? '');
            $blockOrders = [];

            foreach (($page['blocks'] ?? []) as $block) {
                if (!is_array($block)) {
                    continue;
                }

                $blockPageId = (string) ($block['pageId'] ?? '');
                if ($blockPageId !== '' && $pageId !== '' && $blockPageId !== $pageId) {
                    $errors[] = [
                        'code' => 'block_page_mismatch',
                        'message' => 'Block page ID does not match its enclosing page.',
                        'context' => [
                            'blockId' => $block['id'] ?? '',
                            'pageId' => $pageId,
                            'blockPageId' => $blockPageId,
                        ],
                    ];
                }

                $order = $block['order'] ?? null;
                if (is_int($order)) {
                    if (isset($blockOrders[$order])) {
                        $warnings[] = [
                            'code' => 'duplicate_block_order',
                            'message' => 'Blocks on the same page share an order value.',
                            'context' => ['pageId' => $pageId, 'order' => $order],
                        ];
                    }
                    $blockOrders[$order] = true;
                }

                $componentOrders = [];
                foreach (($block['components'] ?? []) as $component) {
                    if (!is_array($component)) {
                        continue;
                    }

                    $componentOrder = $component['order'] ?? null;
                    if (is_int($componentOrder)) {
                        if (isset($componentOrders[$componentOrder])) {
                            $warnings[] = [
                                'code' => 'duplicate_idevice_order',
                                'message' => 'iDevices in the same block share an order value.',
                                'context' => [
                                    'blockId' => $block['id'] ?? '',
                                    'order' => $componentOrder,
                                ],
                            ];
                        }
                        $componentOrders[$componentOrder] = true;
                    }
                }
            }
        }
    }

    /**
     * Validate expected package-level files as warnings.
     *
     * @param ELPParser                       $parser Parsed project.
     * @param array<int, array<string,mixed>> $warnings Validation warnings.
     *
     * @return void
     */
    private function validatePackageBaseline(ELPParser $parser, array &$warnings): void
    {
        if ($parser->getPackageProfile() !== 'elpx-v4') {
            return;
        }

        $entries = $parser->getArchiveEntries();

        foreach (['content.dtd', 'index.html', 'screenshot.png'] as $required) {
            if (!in_array($required, $entries, true)) {
                $warnings[] = [
                    'code' => 'missing_v4_baseline_file',
                    'message' => 'Expected eXeLearning 4 package file is missing.',
                    'context' => ['path' => $required],
                ];
            }
        }

        foreach (['theme/', 'libs/', 'idevices/'] as $prefix) {
            $found = false;
            foreach ($entries as $entry) {
                if (str_starts_with($entry, $prefix)) {
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $warnings[] = [
                    'code' => 'missing_v4_baseline_directory',
                    'message' => 'Expected eXeLearning 4 package directory is missing.',
                    'context' => ['path' => $prefix],
                ];
            }
        }
    }
}
