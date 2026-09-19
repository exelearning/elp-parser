<?php

/**
 * Page.php
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

use JsonSerializable;

/**
 * Typed wrapper around parsed page data.
 */
final class Page implements JsonSerializable
{
    /** @var array<string, mixed> */
    private array $data;

    /** @param array<string, mixed> $data Parsed page data. */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function getId(): string
    {
        return (string) ($this->data['id'] ?? '');
    }

    public function getParentId(): string
    {
        return (string) ($this->data['parentId'] ?? '');
    }

    public function getTitle(): string
    {
        return (string) ($this->data['title'] ?? '');
    }

    /** @return array<int, Block> */
    public function getBlocks(): array
    {
        $blocks = [];

        foreach (($this->data['blocks'] ?? []) as $block) {
            if (is_array($block)) {
                $blocks[] = new Block($block);
            }
        }

        return $blocks;
    }

    /** @return array<int, Idevice> */
    public function getIdevices(): array
    {
        $idevices = [];

        foreach (($this->data['idevices'] ?? []) as $idevice) {
            if (is_array($idevice)) {
                $idevices[] = new Idevice($idevice);
            }
        }

        return $idevices;
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
