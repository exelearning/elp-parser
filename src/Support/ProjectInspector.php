<?php

/**
 * ProjectInspector.php
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

use Exelearning\Archive\ArchiveLimits;
use Exelearning\Archive\ArchiveReader;
use Exelearning\Exception\UnsupportedFormatException;
use SimpleXMLElement;

/**
 * Read lightweight project metadata without normalizing pages, iDevices or assets.
 */
final class ProjectInspector
{
    private string $filePath;
    private ArchiveReader $archiveReader;
    private ArchiveLimits $archiveLimits;

    /**
     * @param string             $filePath Project file path.
     * @param ArchiveLimits|null $limits   Optional archive safety limits.
     */
    public function __construct(string $filePath, ?ArchiveLimits $limits = null)
    {
        $this->filePath = $filePath;
        $this->archiveLimits = $limits ?? new ArchiveLimits();
        $this->archiveReader = new ArchiveReader($filePath, $this->archiveLimits);
    }

    /**
     * Inspect project identity, format and core metadata.
     *
     * @return array<string, mixed>
     */
    public function inspect(): array
    {
        $entries = $this->archiveReader->inspect();
        $extension = strtolower(pathinfo($this->filePath, PATHINFO_EXTENSION));
        $hasRootDtd = in_array('content.dtd', $entries, true);

        if (in_array('contentv3.xml', $entries, true)) {
            return $this->inspectLegacy($entries, $extension);
        }

        if (!in_array('content.xml', $entries, true)) {
            throw new UnsupportedFormatException('Invalid ELP file: No content XML found.');
        }

        $xmlContent = $this->archiveReader->readEntry(
            'content.xml',
            $this->archiveLimits->maxXmlBytes
        );
        $xml = (new XmlLoader())->load($xmlContent);

        return $this->inspectModern(
            $xml,
            $entries,
            $extension,
            $hasRootDtd
        );
    }

    /**
     * Inspect a modern ODE package.
     *
     * @param SimpleXMLElement   $xml        Parsed XML.
     * @param array<int, string> $entries    Archive entries.
     * @param string             $extension  Source extension.
     * @param bool               $hasRootDtd Whether content.dtd exists at archive root.
     *
     * @return array<string, mixed>
     */
    private function inspectModern(
        SimpleXMLElement $xml,
        array $entries,
        string $extension,
        bool $hasRootDtd
    ): array {
        $preferences = $this->readKeyValueNodes(
            $this->xpath($xml, './x:userPreferences/x:userPreference')
        );
        $resources = $this->readKeyValueNodes(
            $this->xpath($xml, './x:odeResources/x:odeResource')
        );
        $properties = $this->readKeyValueNodes(
            $this->xpath($xml, './x:odeProperties/x:odeProperty')
        );

        $applicationVersion = $resources['exe_version']
            ?? ($resources['eXeVersion'] ?? ($properties['pp_exelearning_version'] ?? null));
        $likelyVersion4 = $extension === 'elpx' && $hasRootDtd;
        $versionInfo = (new VersionDetector())->detectModern(
            is_string($applicationVersion) ? $applicationVersion : null,
            $likelyVersion4
        );
        $detectedMajor = (int) $versionInfo['detectedMajor'];
        $prefix = $extension === 'elpx' ? 'elpx' : 'ode';

        return [
            'sourceExtension' => $extension,
            'contentFile' => 'content.xml',
            'contentFormat' => 'ode-content',
            'formatFamily' => 'ode',
            'formatVersion' => isset($xml['version']) ? (string) $xml['version'] : null,
            'applicationVersion' => is_string($applicationVersion) ? $applicationVersion : null,
            'version' => $detectedMajor,
            'versionInfo' => $versionInfo,
            'packageProfile' => $prefix . '-v' . $detectedMajor,
            'title' => (string) ($properties['pp_title'] ?? ''),
            'description' => (string) ($properties['pp_description'] ?? ''),
            'author' => (string) ($properties['pp_author'] ?? ''),
            'license' => (string) ($properties['pp_license'] ?? ($properties['license'] ?? '')),
            'language' => (string) ($properties['pp_lang'] ?? ($properties['lom_general_language'] ?? '')),
            'learningResourceType' => (string) ($properties['pp_learningResourceType'] ?? ''),
            'projectId' => $resources['odeId'] ?? null,
            'projectVersionId' => $resources['odeVersionId'] ?? null,
            'hasRootDtd' => $hasRootDtd,
            'archiveEntriesCount' => count($entries),
            'userPreferences' => $preferences,
        ];
    }

    /**
     * Inspect a legacy contentv3 package without normalizing its page tree.
     *
     * @param array<int, string> $entries   Archive entries.
     * @param string             $extension Source extension.
     *
     * @return array<string, mixed>
     */
    private function inspectLegacy(array $entries, string $extension): array
    {
        $xmlContent = $this->archiveReader->readEntry(
            'contentv3.xml',
            $this->archiveLimits->maxXmlBytes
        );
        $xml = (new XmlLoader())->load($xmlContent);

        return [
            'sourceExtension' => $extension,
            'contentFile' => 'contentv3.xml',
            'contentFormat' => 'legacy-contentv3',
            'formatFamily' => 'legacy',
            'formatVersion' => null,
            'applicationVersion' => null,
            'version' => 2,
            'versionInfo' => [
                'declared' => null,
                'declaredMajor' => null,
                'detectedMajor' => 2,
                'source' => 'format',
            ],
            'packageProfile' => 'legacy-v2',
            'title' => $this->legacyDictionaryValue($xml, '_title'),
            'description' => $this->legacyDictionaryValue($xml, '_description'),
            'author' => $this->legacyDictionaryValue($xml, '_author'),
            'license' => $this->legacyDictionaryValue($xml, 'license'),
            'language' => $this->legacyDictionaryValue($xml, '_lang'),
            'learningResourceType' => $this->legacyDictionaryValue(
                $xml,
                '_learningResourceType'
            ),
            'projectId' => null,
            'projectVersionId' => null,
            'hasRootDtd' => false,
            'archiveEntriesCount' => count($entries),
            'userPreferences' => [],
        ];
    }

    /**
     * Read one top-level legacy dictionary value by key.
     *
     * @param SimpleXMLElement $xml XML document.
     * @param string           $key Legacy dictionary key.
     *
     * @return string
     */
    private function legacyDictionaryValue(SimpleXMLElement $xml, string $key): string
    {
        $safeKey = str_replace("'", "&apos;", $key);
        $matches = $xml->xpath(
            "//*[(@role='key') and (@value='{$safeKey}')]/following-sibling::*[1]"
        );

        if (!is_array($matches) || $matches === []) {
            return '';
        }

        $value = $matches[0];

        return isset($value['value'])
            ? (string) $value['value']
            : trim((string) $value);
    }

    /**
     * Execute XPath with the default namespace mapped to x.
     *
     * @param SimpleXMLElement $node XML node.
     * @param string           $path XPath expression.
     *
     * @return array<int, SimpleXMLElement>
     */
    private function xpath(SimpleXMLElement $node, string $path): array
    {
        $namespaces = $node->getDocNamespaces(true);

        if (isset($namespaces[''])) {
            $node->registerXPathNamespace('x', $namespaces['']);
        } else {
            $path = str_replace('x:', '', $path);
        }

        $result = $node->xpath($path);

        return is_array($result) ? $result : [];
    }

    /**
     * Convert ODE key/value nodes to an associative array.
     *
     * @param array<int, SimpleXMLElement> $nodes Nodes to read.
     *
     * @return array<string, string>
     */
    private function readKeyValueNodes(array $nodes): array
    {
        $values = [];

        foreach ($nodes as $node) {
            $key = isset($node->key) ? trim((string) $node->key) : '';
            if ($key === '') {
                continue;
            }

            $values[$key] = isset($node->value)
                ? trim((string) $node->value)
                : '';
        }

        return $values;
    }
}
