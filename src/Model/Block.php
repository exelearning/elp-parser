<?php

/**
 * Block.php
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
 * Typed wrapper around parsed block data.
 */
final class Block implements JsonSerializable
{
    /** @var array<string, mixed> */
    private array $data;

    /** @param array<string, mixed> $data Parsed block data. */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function getId(): string
    {
        return (string) ($this->data['id'] ?? '');
    }

    public function getName(): string
    {
        return (string) ($this->data['name'] ?? '');
    }

    /** @return array<int, Idevice> */
    public function getIdevices(): array
    {
        $idevices = [];

        foreach (($this->data['components'] ?? []) as $component) {
            if (is_array($component)) {
                $idevices[] = new Idevice($component);
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
