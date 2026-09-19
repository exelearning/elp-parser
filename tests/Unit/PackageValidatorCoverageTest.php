<?php

/**
 * Coverage tests for structural package validation branches.
 *
 * @category Tests
 * @package  ELPParser
 * @author   INTEF <cedec@educacion.gob.es>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/exelearning/elp-parser
 */

namespace Exelearning\ElpParser\Tests\Unit;

use Exelearning\ELPParser;
use Exelearning\Validation\PackageValidator;

it(
    'covers structural validation diagnostics for malformed modern projects',
    function () {
        $parser = new class extends ELPParser {
            public function __construct()
            {
            }

            public function getBrokenReferences(): array
            {
                return [];
            }

            public function getPages(): array
            {
                return [
                    [
                        'id' => '',
                        'title' => 'Missing ID',
                        'parentId' => '',
                        'order' => 1,
                        'blocks' => [],
                    ],
                    [
                        'id' => 'DUP',
                        'title' => 'First duplicate',
                        'parentId' => '',
                        'order' => 2,
                        'blocks' => [
                            [
                                'id' => 'BLOCK-1',
                                'pageId' => 'WRONG-PAGE',
                                'order' => 1,
                                'components' => [
                                    ['id' => 'COMP-1', 'order' => 1],
                                    ['id' => 'COMP-2', 'order' => 1],
                                    'ignored',
                                ],
                            ],
                            [
                                'id' => 'BLOCK-2',
                                'pageId' => 'DUP',
                                'order' => 1,
                                'components' => [],
                            ],
                            'ignored',
                        ],
                    ],
                    [
                        'id' => 'DUP',
                        'title' => 'Second duplicate',
                        'parentId' => '',
                        'order' => 2,
                        'blocks' => [],
                    ],
                    [
                        'id' => 'PAGE-A',
                        'title' => 'Cycle A',
                        'parentId' => 'PAGE-B',
                        'order' => 3,
                        'blocks' => [],
                    ],
                    [
                        'id' => 'PAGE-B',
                        'title' => 'Cycle B',
                        'parentId' => 'PAGE-A',
                        'order' => 4,
                        'blocks' => [],
                    ],
                    [
                        'id' => 'ORPHAN',
                        'title' => 'Missing parent',
                        'parentId' => 'DOES-NOT-EXIST',
                        'order' => 5,
                        'blocks' => [],
                    ],
                ];
            }

            public function getBlocks(): array
            {
                return [
                    ['id' => 'BLOCK-DUP'],
                    ['id' => 'BLOCK-DUP'],
                    ['id' => ''],
                ];
            }

            public function getIdevices(): array
            {
                return [
                    [
                        'id' => 'IDEVICE-DUP',
                        'type' => 'text',
                        'storagePattern' => 'standard-json',
                        'stateDecodeError' => 'Syntax error',
                    ],
                    [
                        'id' => 'IDEVICE-DUP',
                        'type' => 'quiz',
                        'storagePattern' => 'data-game',
                        'stateDecodeError' => null,
                    ],
                    [
                        'id' => '',
                        'type' => '',
                        'stateDecodeError' => '',
                    ],
                ];
            }

            public function getProjectId(): ?string
            {
                return 'bad-project-id';
            }

            public function getProjectVersionId(): ?string
            {
                return 'bad-version-id';
            }

            public function getBrokenInternalLinks(): array
            {
                return [
                    [
                        'targetPageId' => 'MISSING',
                        'pageId' => 'DUP',
                        'ideviceId' => 'IDEVICE-DUP',
                    ],
                ];
            }

            public function isLegacyFormat(): bool
            {
                return false;
            }

            public function getMissingIdeviceRuntimes(): array
            {
                return ['quiz'];
            }

            public function getPackageProfile(): string
            {
                return 'elpx-v4';
            }

            public function getArchiveEntries(): array
            {
                return [
                    'content.xml',
                    'content.dtd',
                    'theme/style.css',
                    'libs/common.js',
                ];
            }
        };

        $result = (new PackageValidator())->validate($parser);
        $errorCodes = array_column($result['errors'], 'code');
        $warningCodes = array_column($result['warnings'], 'code');

        expect($result['valid'])->toBeFalse();
        expect($errorCodes)->toContain('duplicate_page_id');
        expect($errorCodes)->toContain('duplicate_block_id');
        expect($errorCodes)->toContain('duplicate_idevice_id');
        expect($errorCodes)->toContain('missing_parent_page');
        expect($errorCodes)->toContain('page_hierarchy_cycle');
        expect($errorCodes)->toContain('block_page_mismatch');
        expect($errorCodes)->toContain('broken_internal_link');

        expect($warningCodes)->toContain('missing_page_id');
        expect($warningCodes)->toContain('noncanonical_project_id');
        expect($warningCodes)->toContain('duplicate_page_order');
        expect($warningCodes)->toContain('duplicate_block_order');
        expect($warningCodes)->toContain('duplicate_idevice_order');
        expect($warningCodes)->toContain('missing_idevice_runtime');
        expect($warningCodes)->toContain('invalid_idevice_state');
        expect($warningCodes)->toContain('missing_v4_baseline_file');
        expect($warningCodes)->toContain('missing_v4_baseline_directory');
    }
);

it(
    'covers clean legacy validation shortcuts and nullable identifiers',
    function () {
        $parser = new class extends ELPParser {
            public function __construct()
            {
            }

            public function getBrokenReferences(): array
            {
                return [];
            }

            public function getPages(): array
            {
                return [];
            }

            public function getBlocks(): array
            {
                return [];
            }

            public function getIdevices(): array
            {
                return [];
            }

            public function getProjectId(): ?string
            {
                return null;
            }

            public function getProjectVersionId(): ?string
            {
                return null;
            }

            public function getBrokenInternalLinks(): array
            {
                return [];
            }

            public function isLegacyFormat(): bool
            {
                return true;
            }

            public function getMissingIdeviceRuntimes(): array
            {
                return [];
            }

            public function getPackageProfile(): string
            {
                return 'legacy-v2';
            }

            public function getArchiveEntries(): array
            {
                return [];
            }
        };

        $result = (new PackageValidator())->validate($parser);

        expect($result)->toBe([
            'valid' => true,
            'errors' => [],
            'warnings' => [],
        ]);
    }
);
