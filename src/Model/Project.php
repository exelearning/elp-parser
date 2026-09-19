<?php

/**
 * Project.php
 *
 * PHP Version 8.0
 *
 * @category Model
 * @package  Exelearning
 * @author   INTEF <cedec@educacion.gob.es>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/exelearning/elp-parser
 */

namespace Exelearning\Model;

use Exelearning\ELPParser;
use JsonSerializable;

/**
 * Typed project facade backed by the detailed parser representation.
 */
final class Project implements JsonSerializable
{
    /** @var array<string, mixed> */
    private array $data;

    /** @param array<string, mixed> $data Detailed project data. */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public static function fromParser(ELPParser $parser): self
    {
        return new self($parser->toDetailedArray());
    }

    public function getTitle(): string
    {
        return (string) ($this->data['summary']['title'] ?? '');
    }

    public function getFormatFamily(): string
    {
        return (string) ($this->data['format']['family'] ?? '');
    }

    public function getFormatVersion(): ?string
    {
        $value = $this->data['format']['version'] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function getVersionInfo(): VersionInfo
    {
        $data = $this->data['versionInfo'] ?? [];

        return new VersionInfo(is_array($data) ? $data : []);
    }

    /** @return array<int, Page> */
    public function getPages(): array
    {
        $pages = [];

        foreach (($this->data['pages'] ?? []) as $page) {
            if (is_array($page)) {
                $pages[] = new Page($page);
            }
        }

        return $pages;
    }

    /** @return array<int, Asset> */
    public function getAssets(): array
    {
        $assets = [];

        foreach (($this->data['assets'] ?? []) as $asset) {
            if (is_array($asset)) {
                $assets[] = new Asset($asset);
            }
        }

        return $assets;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->data;
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }
}
