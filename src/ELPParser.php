<?php

/**
 * ELPParser.php
 *
 * PHP Version 8.0
 *
 * @category Parser
 * @package  Exelearning
 * @author   INTEF <cedec@educacion.gob.es>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/exelearning/elp-parser
 */

namespace Exelearning;

use Exelearning\Archive\ArchiveLimits;
use Exelearning\Archive\ArchiveReader;
use Exelearning\Asset\AssetReferenceExtractor;
use Exelearning\Exception\ElpParserException;
use Exelearning\Exception\UnsupportedFormatException;
use Exelearning\Model\Project;
use Exelearning\Parser\LegacyParser;
use Exelearning\Parser\OdeParser;
use Exelearning\Reference\InternalReferenceExtractor;
use Exelearning\Support\ProjectInspector;
use Exelearning\Support\VersionDetector;
use Exelearning\Support\XmlLoader;
use Exelearning\Validation\PackageValidator;
use Exelearning\Validation\SchemaValidator;
use JsonException;
use JsonSerializable;

/**
 * Parser for eXeLearning project files.
 *
 * Supported project formats:
 * - Legacy .elp packages based on contentv3.xml from eXeLearning 2.x.
 * - Modern .elp/.elpx packages based on content.xml (ODE 2.0) from eXeLearning 3+.
 *
 * @category Parser
 * @package  Exelearning
 * @author   INTEF <cedec@educacion.gob.es>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/exelearning/elp-parser
 */
class ELPParser implements JsonSerializable
{
    protected string $filePath;
    protected int $version = 2;
    protected string $sourceExtension = '';
    protected string $contentFormat = '';
    protected string $contentFile = '';
    protected ?string $contentSchemaVersion = null;
    protected ?string $exeVersion = null;

    /** @var array<int, string> */
    protected array $archiveEntries = [];

    protected bool $hasRootDtd = false;
    protected string $resourceLayout = 'none';
    protected string $resourceProfile = 'none';

    /** @var array<string, mixed> */
    protected array $legacyData = [];

    /** @var array<string, string> */
    protected array $odeProperties = [];

    /** @var array<string, string> */
    protected array $odeResources = [];

    /** @var array<string, string> */
    protected array $userPreferences = [];

    /** @var array<int, string> */
    protected array $strings = [];

    /** @var array<int, array<string, mixed>> */
    protected array $pages = [];

    /** @var array<int, string> */
    protected array $assets = [];

    /** @var array<int, array<string, mixed>> */
    protected array $assetsDetailed = [];

    /** @var array<string, mixed> */
    protected array $versionInfo = [];

    protected string $title = '';
    protected string $description = '';
    protected string $author = '';
    protected string $license = '';
    protected string $language = '';
    protected string $learningResourceType = '';

    private ArchiveLimits $archiveLimits;
    private ArchiveReader $archiveReader;
    private AssetReferenceExtractor $assetExtractor;
    private InternalReferenceExtractor $internalReferenceExtractor;

    /**
     * Create a new parser instance.
     *
     * @param string             $filePath Project file path.
     * @param ArchiveLimits|null $limits   Optional archive safety limits.
     */
    public function __construct(string $filePath, ?ArchiveLimits $limits = null)
    {
        $this->filePath = $filePath;
        $this->sourceExtension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $this->archiveLimits = $limits ?? new ArchiveLimits();
        $this->archiveReader = new ArchiveReader($filePath, $this->archiveLimits);
        $this->parse();
    }

    /**
     * Create a parser from a file path.
     *
     * @param string             $filePath Project file path.
     * @param ArchiveLimits|null $limits   Optional archive safety limits.
     *
     * @return self
     */
    public static function fromFile(string $filePath, ?ArchiveLimits $limits = null): self
    {
        return new self($filePath, $limits);
    }

    /**
     * Inspect core project metadata without fully normalizing page content.
     *
     * @param string             $filePath Project file path.
     * @param ArchiveLimits|null $limits   Optional archive safety limits.
     *
     * @return array<string, mixed>
     */
    public static function inspect(string $filePath, ?ArchiveLimits $limits = null): array
    {
        return (new ProjectInspector($filePath, $limits))->inspect();
    }

    /**
     * Detect the project format and parse its contents.
     *
     * @return void
     */
    protected function parse(): void
    {
        $this->archiveEntries = $this->archiveReader->inspect();
        $this->hasRootDtd = in_array('content.dtd', $this->archiveEntries, true);
        $this->resourceLayout = $this->detectResourceLayout($this->archiveEntries);
        $this->resourceProfile = $this->detectResourceProfile($this->archiveEntries);

        if (in_array('contentv3.xml', $this->archiveEntries, true)) {
            $this->contentFormat = 'legacy-contentv3';
            $this->contentFile = 'contentv3.xml';
            $this->version = 2;
            $this->versionInfo = [
                'declared' => null,
                'declaredMajor' => null,
                'detectedMajor' => 2,
                'source' => 'format',
                'signals' => $this->versionSignals(),
            ];
        } elseif (in_array('content.xml', $this->archiveEntries, true)) {
            $this->contentFormat = 'ode-content';
            $this->contentFile = 'content.xml';
        } else {
            throw new UnsupportedFormatException('Invalid ELP file: No content XML found.');
        }

        $xmlContent = $this->archiveReader->readEntry($this->contentFile, $this->archiveLimits->maxXmlBytes);
        $xml = (new XmlLoader())->load($xmlContent);

        if ($this->isLegacyFormat()) {
            $parsed = (new LegacyParser())->parse($xml);
            $this->legacyData = is_array($parsed['data'] ?? null) ? $parsed['data'] : [];
            $this->hydrateCommonData($parsed);
        } else {
            $parsed = (new OdeParser())->parse($xml);
            $this->contentSchemaVersion = is_string($parsed['schemaVersion'] ?? null)
                ? $parsed['schemaVersion']
                : null;
            $this->userPreferences = is_array($parsed['userPreferences'] ?? null)
                ? $parsed['userPreferences']
                : [];
            $this->odeProperties = is_array($parsed['properties'] ?? null) ? $parsed['properties'] : [];
            $this->odeResources = is_array($parsed['resources'] ?? null) ? $parsed['resources'] : [];
            $this->exeVersion = is_string($parsed['exeVersion'] ?? null) ? $parsed['exeVersion'] : null;
            $this->hydrateCommonData($parsed);

            $this->versionInfo = (new VersionDetector())->detectModern(
                $this->exeVersion,
                $this->isLikelyVersion4Package()
            );
            $this->versionInfo['signals'] = $this->versionSignals();
            $this->version = (int) $this->versionInfo['detectedMajor'];
        }

        $this->assetExtractor = new AssetReferenceExtractor($this->archiveEntries);
        $this->internalReferenceExtractor = new InternalReferenceExtractor();
        $this->assetsDetailed = $this->assetExtractor->extract($this->pages);
        $this->assets = array_map(
            static fn(array $asset): string => (string) $asset['path'],
            $this->assetsDetailed
        );
        sort($this->assets);
    }

    /**
     * Hydrate common parser fields from a format-specific parser result.
     *
     * @param array<string, mixed> $parsed Parsed data.
     *
     * @return void
     */
    private function hydrateCommonData(array $parsed): void
    {
        $this->title = (string) ($parsed['title'] ?? '');
        $this->description = (string) ($parsed['description'] ?? '');
        $this->author = (string) ($parsed['author'] ?? '');
        $this->license = (string) ($parsed['license'] ?? '');
        $this->language = (string) ($parsed['language'] ?? '');
        $this->learningResourceType = (string) ($parsed['learningResourceType'] ?? '');
        $this->strings = is_array($parsed['strings'] ?? null) ? $parsed['strings'] : [];
        $this->pages = is_array($parsed['pages'] ?? null) ? $parsed['pages'] : [];
    }

    /**
     * Detect the archive resource layout family.
     *
     * @param array<int, string> $entries Archive entry names.
     *
     * @return string
     */
    private function detectResourceLayout(array $entries): string
    {
        $hasContentResources = false;
        $hasLegacyTempPaths = false;

        foreach ($entries as $entry) {
            if (str_starts_with($entry, 'content/resources/')) {
                $hasContentResources = true;
            }

            if (str_starts_with($entry, 'files/tmp/')) {
                $hasLegacyTempPaths = true;
            }
        }

        if ($hasContentResources && $hasLegacyTempPaths) {
            return 'mixed';
        }

        if ($hasContentResources) {
            return 'content-resources';
        }

        if ($hasLegacyTempPaths) {
            return 'legacy-temp-paths';
        }

        return 'none';
    }

    /**
     * Detect the resource layout profile used by modern packages.
     *
     * eXeLearning 3 commonly stored assets in per-asset ODE-ID directories,
     * while eXeLearning 4 stores assets directly under content/resources/
     * and preserves user-created folders.
     *
     * @param array<int, string> $entries Archive entry names.
     *
     * @return string
     */
    private function detectResourceProfile(array $entries): string
    {
        $hasV3UuidResources = false;
        $hasV4TreeResources = false;
        $hasLegacyTempPaths = false;

        foreach ($entries as $entry) {
            if (str_starts_with($entry, 'files/tmp/')) {
                $hasLegacyTempPaths = true;
            }

            if (!str_starts_with($entry, 'content/resources/') || str_ends_with($entry, '/')) {
                continue;
            }

            $relativePath = substr($entry, strlen('content/resources/'));
            $firstSegment = explode('/', $relativePath, 2)[0] ?? '';

            if (preg_match('/^[0-9]{14}[A-Z0-9]{6}$/', $firstSegment) === 1) {
                $hasV3UuidResources = true;
            } else {
                $hasV4TreeResources = true;
            }
        }

        if ($hasV3UuidResources && $hasV4TreeResources) {
            return 'mixed-modern-resources';
        }

        if ($hasV3UuidResources) {
            return 'v3-uuid-resources';
        }

        if ($hasV4TreeResources) {
            return 'v4-resource-tree';
        }

        if ($hasLegacyTempPaths) {
            return 'legacy-temp-paths';
        }

        return 'none';
    }

    /**
     * Return signals used by version detection.
     *
     * @return array<string, mixed>
     */
    private function versionSignals(): array
    {
        return [
            'extension' => $this->sourceExtension,
            'contentFormat' => $this->contentFormat,
            'contentFile' => $this->contentFile,
            'rootDtd' => $this->hasRootDtd,
            'resourceLayout' => $this->resourceLayout,
            'resourceProfile' => $this->resourceProfile,
        ];
    }

    /**
     * Get the detected eXeLearning major version.
     *
     * @return int
     */
    public function getVersion(): int
    {
        return $this->version;
    }

    /**
     * Get version detection details and signals.
     *
     * @return array<string, mixed>
     */
    public function getVersionInfo(): array
    {
        return $this->versionInfo;
    }

    /**
     * Get the source project extension.
     *
     * @return string
     */
    public function getSourceExtension(): string
    {
        return $this->sourceExtension;
    }

    /**
     * Get the detected content format identifier.
     *
     * @return string
     */
    public function getContentFormat(): string
    {
        return $this->contentFormat;
    }

    /**
     * Get the broad project format family.
     *
     * @return string
     */
    public function getFormatFamily(): string
    {
        return $this->isLegacyFormat() ? 'legacy' : 'ode';
    }

    /**
     * Get the internal format version independently from the application version.
     *
     * @return string|null
     */
    public function getFormatVersion(): ?string
    {
        return $this->isLegacyFormat() ? null : $this->contentSchemaVersion;
    }

    /**
     * Get the XML entry name used by the package.
     *
     * @return string
     */
    public function getContentFile(): string
    {
        return $this->contentFile;
    }

    /**
     * Get the ODE schema version when available.
     *
     * @return string|null
     */
    public function getContentSchemaVersion(): ?string
    {
        return $this->contentSchemaVersion;
    }

    /**
     * Get the raw eXeLearning version string when available.
     *
     * @return string|null
     */
    public function getExeVersion(): ?string
    {
        return $this->exeVersion;
    }

    /**
     * Get the raw eXeLearning application version declared by the package.
     *
     * @return string|null
     */
    public function getApplicationVersion(): ?string
    {
        return $this->exeVersion;
    }

    /**
     * Get modern ODE user preferences.
     *
     * @return array<string, string>
     */
    public function getUserPreferences(): array
    {
        return $this->userPreferences;
    }

    /**
     * Get modern ODE resources.
     *
     * @return array<string, string>
     */
    public function getOdeResources(): array
    {
        return $this->odeResources;
    }

    /**
     * Get modern ODE properties.
     *
     * @return array<string, string>
     */
    public function getOdeProperties(): array
    {
        return $this->odeProperties;
    }

    /**
     * Get the stable ODE project identifier.
     *
     * @return string|null
     */
    public function getProjectId(): ?string
    {
        $projectId = $this->odeResources['odeId'] ?? null;

        return is_string($projectId) && $projectId !== '' ? $projectId : null;
    }

    /**
     * Get the ODE project-version identifier.
     *
     * @return string|null
     */
    public function getProjectVersionId(): ?string
    {
        $versionId = $this->odeResources['odeVersionId'] ?? null;

        return is_string($versionId) && $versionId !== '' ? $versionId : null;
    }

    /**
     * Get a compatibility profile for the parsed package.
     *
     * @return string
     */
    public function getPackageProfile(): string
    {
        if ($this->isLegacyFormat()) {
            return 'legacy-v2';
        }

        $prefix = $this->sourceExtension === 'elpx' ? 'elpx' : 'ode';

        return $this->version >= 3
            ? $prefix . '-v' . $this->version
            : $prefix . '-modern';
    }

    /**
     * Determine whether the project uses the legacy contentv3 format.
     *
     * @return bool
     */
    public function isLegacyFormat(): bool
    {
        return $this->contentFormat === 'legacy-contentv3';
    }

    /**
     * Return whether the package contains a root content.dtd entry.
     *
     * @return bool
     */
    public function hasRootDtd(): bool
    {
        return $this->hasRootDtd;
    }

    /**
     * Return the detected resource layout family.
     *
     * @return string
     */
    public function getResourceLayout(): string
    {
        return $this->resourceLayout;
    }

    /**
     * Get the detected modern resource storage profile.
     *
     * @return string
     */
    public function getResourceProfile(): string
    {
        return $this->resourceProfile;
    }

    /**
     * Heuristically identify likely eXeLearning 4-style packages.
     *
     * @return bool
     */
    public function isLikelyVersion4Package(): bool
    {
        return $this->contentFormat === 'ode-content'
            && $this->sourceExtension === 'elpx'
            && $this->hasRootDtd;
    }

    /**
     * Get all extracted strings.
     *
     * @return array<int, string>
     */
    public function getStrings(): array
    {
        return $this->strings;
    }

    /**
     * Get parsed page information.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getPages(): array
    {
        return $this->pages;
    }

    /**
     * Get a page by its identifier.
     *
     * @param string $pageId Page identifier.
     *
     * @return array<string, mixed>|null
     */
    public function getPageById(string $pageId): ?array
    {
        foreach ($this->pages as $page) {
            if (($page['id'] ?? '') === $pageId) {
                return $page;
            }
        }

        return null;
    }

    /**
     * Get pages as a nested tree while preserving the flat getPages() API.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getPageTree(): array
    {
        $pagesById = [];
        $childrenByParent = [];

        foreach ($this->pages as $page) {
            $id = (string) ($page['id'] ?? '');
            if ($id === '') {
                continue;
            }

            $pagesById[$id] = $page;
            $parentId = (string) ($page['parentId'] ?? '');
            $childrenByParent[$parentId][] = $id;
        }

        $rootIds = [];
        foreach ($pagesById as $id => $page) {
            $parentId = (string) ($page['parentId'] ?? '');
            if ($parentId === '' || !isset($pagesById[$parentId])) {
                $rootIds[] = $id;
            }
        }

        $tree = [];
        foreach ($rootIds as $rootId) {
            $tree[] = $this->buildPageTreeNode($rootId, $pagesById, $childrenByParent, []);
        }

        return $tree;
    }

    /**
     * Get only visible pages.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getVisiblePages(): array
    {
        return array_values(
            array_filter(
                $this->pages,
                static fn(array $page): bool => ($page['visible'] ?? true) === true
            )
        );
    }

    /**
     * Get all blocks across all pages.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getBlocks(): array
    {
        $blocks = [];

        foreach ($this->pages as $page) {
            foreach (($page['blocks'] ?? []) as $block) {
                if (!is_array($block)) {
                    continue;
                }

                $blocks[] = $block + ['pageTitle' => $page['title'] ?? ''];
            }
        }

        return $blocks;
    }

    /**
     * Get a block by its identifier.
     *
     * @param string $blockId Block identifier.
     *
     * @return array<string, mixed>|null
     */
    public function getBlockById(string $blockId): ?array
    {
        foreach ($this->getBlocks() as $block) {
            if (($block['id'] ?? '') === $blockId) {
                return $block;
            }
        }

        return null;
    }

    /**
     * Get all iDevices across all pages.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getIdevices(): array
    {
        $idevices = [];

        foreach ($this->pages as $page) {
            foreach (($page['idevices'] ?? []) as $idevice) {
                if (!is_array($idevice)) {
                    continue;
                }

                $idevices[] = $idevice + [
                    'pageId' => $page['id'] ?? '',
                    'pageTitle' => $page['title'] ?? '',
                ];
            }
        }

        return $idevices;
    }

    /**
     * Get an iDevice by its identifier.
     *
     * @param string $ideviceId iDevice identifier.
     *
     * @return array<string, mixed>|null
     */
    public function getIdeviceById(string $ideviceId): ?array
    {
        foreach ($this->getIdevices() as $idevice) {
            if (($idevice['id'] ?? '') === $ideviceId) {
                return $idevice;
            }
        }

        return null;
    }

    /**
     * Get grouped text content for each page.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getPageTexts(): array
    {
        $pageTexts = [];

        foreach ($this->pages as $page) {
            $texts = [];

            foreach (($page['idevices'] ?? []) as $idevice) {
                if (!is_array($idevice)) {
                    continue;
                }

                $text = trim((string) ($idevice['text'] ?? ''));
                if ($text !== '') {
                    $texts[] = $text;
                }
            }

            $pageTexts[] = [
                'id' => $page['id'] ?? '',
                'title' => $page['title'] ?? '',
                'pageName' => $page['pageName'] ?? '',
                'visible' => $page['visible'] ?? true,
                'texts' => $texts,
                'text' => trim(implode("\n\n", $texts)),
            ];
        }

        return $pageTexts;
    }

    /**
     * Get grouped text content for visible pages only.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getVisiblePageTexts(): array
    {
        return array_values(
            array_filter(
                $this->getPageTexts(),
                static fn(array $pageText): bool => ($pageText['visible'] ?? true) === true
            )
        );
    }

    /**
     * Get grouped text content for a single page by its ID.
     *
     * @param string $pageId Page identifier.
     *
     * @return array<string, mixed>|null
     */
    public function getPageTextById(string $pageId): ?array
    {
        foreach ($this->getPageTexts() as $pageText) {
            if (($pageText['id'] ?? '') === $pageId) {
                return $pageText;
            }
        }

        return null;
    }

    /**
     * Get iDevices marked as teacher-only.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getTeacherOnlyIdevices(): array
    {
        return array_values(
            array_filter(
                $this->getIdevices(),
                static fn(array $idevice): bool => ($idevice['teacherOnly'] ?? false) === true
            )
        );
    }

    /**
     * Get hidden iDevices.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getHiddenIdevices(): array
    {
        return array_values(
            array_filter(
                $this->getIdevices(),
                static fn(array $idevice): bool => ($idevice['visible'] ?? true) === false
            )
        );
    }

    /**
     * Get referenced asset paths.
     *
     * @return array<int, string>
     */
    public function getAssets(): array
    {
        return $this->assets;
    }

    /**
     * Get detailed asset information.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAssetsDetailed(): array
    {
        return $this->assetsDetailed;
    }

    /**
     * Get image asset paths.
     *
     * @return array<int, string>
     */
    public function getImages(): array
    {
        return $this->filterAssetPathsByType('image');
    }

    /**
     * Get audio asset paths.
     *
     * @return array<int, string>
     */
    public function getAudioFiles(): array
    {
        return $this->filterAssetPathsByType('audio');
    }

    /**
     * Get video asset paths.
     *
     * @return array<int, string>
     */
    public function getVideoFiles(): array
    {
        return $this->filterAssetPathsByType('video');
    }

    /**
     * Get document asset paths.
     *
     * @return array<int, string>
     */
    public function getDocuments(): array
    {
        return $this->filterAssetPathsByType('document');
    }

    /**
     * Get archive assets that are not referenced in parsed content.
     *
     * @return array<int, string>
     */
    public function getOrphanAssets(): array
    {
        $referenced = array_fill_keys($this->assets, true);
        $orphans = [];

        foreach ($this->archiveEntries as $entry) {
            if (str_ends_with($entry, '/')) {
                continue;
            }

            $type = $this->assetExtractor->detectAssetType($entry);
            if (!in_array($type, ['image', 'audio', 'video', 'document', 'archive'], true)) {
                continue;
            }

            if (!isset($referenced[$entry])) {
                $orphans[] = $entry;
            }
        }

        sort($orphans);

        return $orphans;
    }

    /**
     * Get unresolved asset references with their page and iDevice origins.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getBrokenReferences(): array
    {
        return $this->assetExtractor->findBrokenReferences($this->pages);
    }

    /**
     * Get unique unresolved asset reference strings.
     *
     * @return array<int, string>
     */
    public function getMissingAssets(): array
    {
        $references = array_map(
            static fn(array $reference): string => (string) ($reference['reference'] ?? ''),
            $this->getBrokenReferences()
        );

        $references = array_values(array_unique(array_filter($references)));
        sort($references);

        return $references;
    }

    /**
     * Filter asset paths by logical type.
     *
     * @param string $type Asset type.
     *
     * @return array<int, string>
     */
    protected function filterAssetPathsByType(string $type): array
    {
        $paths = [];

        foreach ($this->assetsDetailed as $asset) {
            if (($asset['type'] ?? null) === $type) {
                $paths[] = (string) $asset['path'];
            }
        }

        sort($paths);

        return $paths;
    }

    /**
     * Get archive entry names.
     *
     * @return array<int, string>
     */
    public function getArchiveEntries(): array
    {
        return $this->archiveEntries;
    }

    /**
     * Get internal exe-node page references with their origins.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getInternalLinks(): array
    {
        return $this->internalReferenceExtractor->extract($this->pages);
    }

    /**
     * Get internal page references whose target does not exist.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getBrokenInternalLinks(): array
    {
        $pageIds = [];

        foreach ($this->pages as $page) {
            $id = (string) ($page['id'] ?? '');
            if ($id !== '') {
                $pageIds[$id] = true;
            }
        }

        return array_values(
            array_filter(
                $this->getInternalLinks(),
                static fn(array $link): bool => !isset(
                    $pageIds[(string) ($link['targetPageId'] ?? '')]
                )
            )
        );
    }

    /**
     * Get the iDevice types used by parsed content.
     *
     * @return array<int, string>
     */
    public function getUsedIdeviceTypes(): array
    {
        $types = [];

        foreach ($this->getIdevices() as $idevice) {
            $type = (string) ($idevice['type'] ?? '');
            if ($type !== '') {
                $types[$type] = true;
            }
        }

        $types = array_keys($types);
        sort($types);

        return $types;
    }

    /**
     * Get iDevice runtime directories available in the package.
     *
     * @return array<int, string>
     */
    public function getAvailableIdeviceTypes(): array
    {
        $types = [];

        foreach ($this->archiveEntries as $entry) {
            if (preg_match('#^idevices/([^/]+)/#', $entry, $matches) === 1) {
                $types[(string) $matches[1]] = true;
            }
        }

        $types = array_keys($types);
        sort($types);

        return $types;
    }

    /**
     * Get used iDevice types without a matching packaged runtime directory.
     *
     * @return array<int, string>
     */
    public function getMissingIdeviceRuntimes(): array
    {
        return array_values(
            array_diff(
                $this->getUsedIdeviceTypes(),
                $this->getAvailableIdeviceTypes()
            )
        );
    }

    /**
     * Build a categorized manifest of package entries.
     *
     * @return array<string, mixed>
     */
    public function getPackageManifest(): array
    {
        $manifest = [
            'rootFiles' => [],
            'themeFiles' => [],
            'libraryFiles' => [],
            'ideviceFiles' => [],
            'resourceFiles' => [],
            'otherFiles' => [],
        ];

        foreach ($this->archiveEntries as $entry) {
            if (str_ends_with($entry, '/')) {
                continue;
            }

            if (!str_contains($entry, '/')) {
                $manifest['rootFiles'][] = $entry;
                continue;
            }

            if (str_starts_with($entry, 'theme/')) {
                $manifest['themeFiles'][] = $entry;
                continue;
            }

            if (str_starts_with($entry, 'libs/')) {
                $manifest['libraryFiles'][] = $entry;
                continue;
            }

            if (preg_match('#^idevices/([^/]+)/#', $entry, $matches) === 1) {
                $type = (string) $matches[1];
                $manifest['ideviceFiles'][$type][] = $entry;
                continue;
            }

            if (str_starts_with($entry, 'content/resources/')) {
                $manifest['resourceFiles'][] = $entry;
                continue;
            }

            $manifest['otherFiles'][] = $entry;
        }

        foreach (['rootFiles', 'themeFiles', 'libraryFiles', 'resourceFiles', 'otherFiles'] as $key) {
            sort($manifest[$key]);
        }

        ksort($manifest['ideviceFiles']);

        return $manifest + [
            'usedIdeviceTypes' => $this->getUsedIdeviceTypes(),
            'availableIdeviceTypes' => $this->getAvailableIdeviceTypes(),
            'missingIdeviceRuntimes' => $this->getMissingIdeviceRuntimes(),
        ];
    }

    /**
     * Get the project title.
     *
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Get the project description.
     *
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Get the project author.
     *
     * @return string
     */
    public function getAuthor(): string
    {
        return $this->author;
    }

    /**
     * Get the project license.
     *
     * @return string
     */
    public function getLicense(): string
    {
        return $this->license;
    }

    /**
     * Get the project language.
     *
     * @return string
     */
    public function getLanguage(): string
    {
        return $this->language;
    }

    /**
     * Get the learning resource type.
     *
     * @return string
     */
    public function getLearningResourceType(): string
    {
        return $this->learningResourceType;
    }

    /**
     * Convert parser data to a compact array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'version' => $this->version,
            'title' => $this->title,
            'description' => $this->description,
            'author' => $this->author,
            'license' => $this->license,
            'language' => $this->language,
            'learningResourceType' => $this->learningResourceType,
            'strings' => $this->strings,
        ];
    }

    /**
     * Convert all parsed project information to a detailed array.
     *
     * @return array<string, mixed>
     */
    /**
     * Get a typed project model without replacing the existing array APIs.
     *
     * @return Project
     */
    public function getProject(): Project
    {
        return Project::fromParser($this);
    }

    public function toDetailedArray(): array
    {
        return [
            'summary' => $this->toArray(),
            'format' => [
                'family' => $this->getFormatFamily(),
                'version' => $this->getFormatVersion(),
                'contentFormat' => $this->contentFormat,
                'contentFile' => $this->contentFile,
                'sourceExtension' => $this->sourceExtension,
                'packageProfile' => $this->getPackageProfile(),
                'resourceLayout' => $this->resourceLayout,
                'resourceProfile' => $this->resourceProfile,
            ],
            'versionInfo' => $this->versionInfo,
            'metadata' => $this->getMetadata(),
            'userPreferences' => $this->userPreferences,
            'odeResources' => $this->odeResources,
            'odeProperties' => $this->odeProperties,
            'pages' => $this->pages,
            'pageTree' => $this->getPageTree(),
            'blocks' => $this->getBlocks(),
            'idevices' => $this->getIdevices(),
            'assets' => $this->assetsDetailed,
            'orphanAssets' => $this->getOrphanAssets(),
            'missingAssets' => $this->getMissingAssets(),
            'brokenReferences' => $this->getBrokenReferences(),
            'internalLinks' => $this->getInternalLinks(),
            'brokenInternalLinks' => $this->getBrokenInternalLinks(),
            'packageManifest' => $this->getPackageManifest(),
            'archiveEntries' => $this->archiveEntries,
        ];
    }

    /**
     * Return the JSON-serializable representation.
     *
     * @return mixed
     */
    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    /**
     * Export parsed data as JSON string or file.
     *
     * @param string|null $destinationPath Optional destination path.
     *
     * @return string
     */
    public function exportJson(?string $destinationPath = null): string
    {
        try {
            $json = json_encode(
                $this,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            );
        } catch (JsonException $exception) {
            throw new ElpParserException('Failed to encode JSON: ' . $exception->getMessage(), 0, $exception);
        }

        if ($destinationPath !== null && file_put_contents($destinationPath, $json) === false) {
            throw new ElpParserException('Unable to write JSON file.');
        }

        return $json;
    }

    /**
     * Export the detailed parsed representation as JSON.
     *
     * @param string|null $destinationPath Optional destination path.
     *
     * @return string
     */
    public function exportDetailedJson(?string $destinationPath = null): string
    {
        try {
            $json = json_encode(
                $this->toDetailedArray(),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            );
        } catch (JsonException $exception) {
            throw new ElpParserException('Failed to encode JSON: ' . $exception->getMessage(), 0, $exception);
        }

        if ($destinationPath !== null && file_put_contents($destinationPath, $json) === false) {
            throw new ElpParserException('Unable to write JSON file.');
        }

        return $json;
    }

    /**
     * Get normalized metadata information.
     *
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return [
            'metadata' => $this->isLegacyFormat()
                ? $this->buildLegacyMetadata()
                : $this->buildModernMetadata(),
        ];
    }

    /**
     * Build normalized legacy metadata output.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function buildLegacyMetadata(): array
    {
        $data = $this->legacyData;

        $meta = [
            [
                'schema' => 'Package',
                'content' => [
                    'title' => $data['_title'] ?? '',
                    'lang' => $data['_lang'] ?? '',
                    'description' => [
                        'general_description' => $data['_description'] ?? '',
                        'objectives' => $data['_objectives'] ?? '',
                        'preknowledge' => $data['_preknowledge'] ?? '',
                    ],
                    'author' => $data['_author'] ?? '',
                    'license' => $data['license'] ?? '',
                    'learningResourceType' => $data['_learningResourceType'] ?? '',
                    'usage' => [
                        'intendedEndUserRoleType' => $data['_intendedEndUserRoleType'] ?? '',
                        'intendedEndUserRoleGroup' => $data['_intendedEndUserRoleGroup'] ?? '',
                        'intendedEndUserRoleTutor' => $data['_intendedEndUserRoleTutor'] ?? '',
                        'contextPlace' => $data['_contextPlace'] ?? '',
                        'contextMode' => $data['_contextMode'] ?? '',
                    ],
                    'project_properties' => [
                        'backgroundImg' => $data['_backgroundImg'] ?? '',
                        'backgroundImgTile' => $data['backgroundImgTile'] ?? '',
                        'footer' => $data['footer'] ?? '',
                    ],
                    'format' => [
                        'Doctype' => $data['_docType'] ?? '',
                    ],
                    'taxonomy' => [
                        'level_1' => $data['_levelNames'][0] ?? '',
                        'level_2' => $data['_levelNames'][1] ?? '',
                        'level_3' => $data['_levelNames'][2] ?? '',
                    ],
                    'advanced_options' => [
                        'custom_head' => $data['_extraHeadContent'] ?? '',
                    ],
                ],
            ],
        ];

        foreach (['dublinCore' => 'Dublin core', 'lom' => 'LOM v1.0', 'lomEs' => 'LOM-ES v1.0'] as $key => $schema) {
            if (isset($data[$key])) {
                $meta[] = [
                    'schema' => $schema,
                    'content' => $data[$key] ?? [],
                ];
            }
        }

        return $meta;
    }

    /**
     * Build normalized modern metadata output.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function buildModernMetadata(): array
    {
        return [
            [
                'schema' => 'Package',
                'content' => [
                    'title' => $this->title,
                    'lang' => $this->language,
                    'description' => [
                        'general_description' => $this->description,
                        'objectives' => '',
                        'preknowledge' => '',
                    ],
                    'author' => $this->author,
                    'license' => $this->license,
                    'learningResourceType' => $this->learningResourceType,
                    'format' => [
                        'container' => $this->sourceExtension,
                        'content_file' => $this->contentFile,
                        'content_format' => $this->contentFormat,
                        'format_family' => $this->getFormatFamily(),
                        'schema_version' => $this->contentSchemaVersion ?? '',
                        'format_version' => $this->getFormatVersion() ?? '',
                        'application_version' => $this->getApplicationVersion() ?? '',
                        'package_profile' => $this->getPackageProfile(),
                        'resource_layout' => $this->resourceLayout,
                        'resource_profile' => $this->resourceProfile,
                        'has_root_dtd' => $this->hasRootDtd,
                        'likely_version_4' => $this->isLikelyVersion4Package(),
                    ],
                    'user_preferences' => $this->userPreferences,
                    'project_properties' => $this->odeProperties,
                    'project_resources' => $this->odeResources,
                ],
            ],
        ];
    }

    /**
     * Build one page-tree node and guard against malformed cycles.
     *
     * @param string                                  $pageId           Page identifier.
     * @param array<string, array<string, mixed>>     $pagesById        Pages indexed by ID.
     * @param array<string, array<int, string>>       $childrenByParent Child IDs by parent ID.
     * @param array<string, bool>                     $ancestors        Current ancestor set.
     *
     * @return array<string, mixed>
     */
    private function buildPageTreeNode(
        string $pageId,
        array $pagesById,
        array $childrenByParent,
        array $ancestors
    ): array {
        $page = $pagesById[$pageId];
        $page['children'] = [];

        if (isset($ancestors[$pageId])) {
            $page['cycleDetected'] = true;
            return $page;
        }

        $ancestors[$pageId] = true;

        foreach (($childrenByParent[$pageId] ?? []) as $childId) {
            if (isset($pagesById[$childId])) {
                $page['children'][] = $this->buildPageTreeNode(
                    $childId,
                    $pagesById,
                    $childrenByParent,
                    $ancestors
                );
            }
        }

        return $page;
    }

    /**
     * Validate project structure and package consistency.
     *
     * @return array{valid:bool,errors:array<int,array<string,mixed>>,warnings:array<int,array<string,mixed>>}
     */
    public function validate(): array
    {
        return $this->validatePackage();
    }

    /**
     * Validate project structure and package consistency.
     *
     * @return array{valid:bool,errors:array<int,array<string,mixed>>,warnings:array<int,array<string,mixed>>}
     */
    public function validatePackage(): array
    {
        return (new PackageValidator())->validate($this);
    }

    /**
     * Validate project XML against a caller-supplied trusted local schema.
     *
     * @param string $schemaPath Trusted local XSD or DTD path.
     * @param string $type       Schema type, xsd or dtd.
     *
     * @return array{valid:bool,errors:array<int,array<string,mixed>>}
     */
    public function validateSchema(
        string $schemaPath,
        string $type = SchemaValidator::TYPE_XSD
    ): array {
        $xml = $this->archiveReader->readEntry(
            $this->contentFile,
            $this->archiveLimits->maxXmlBytes
        );

        return (new SchemaValidator())->validate($xml, $schemaPath, $type);
    }

    /**
     * Extract the project contents to a directory.
     *
     * @param string $destinationPath Destination directory.
     *
     * @return void
     */
    public function extract(string $destinationPath): void
    {
        $this->archiveReader->extract($destinationPath);
    }
}
