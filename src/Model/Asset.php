<?php

/**
 * Asset.php
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
 * Typed wrapper around parsed asset data.
 */
final class Asset implements JsonSerializable
{
    /** @var array<string, mixed> */
    private array $data;

    /** @param array<string, mixed> $data Parsed asset data. */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function getPath(): string
    {
        return (string) ($this->data['path'] ?? '');
    }

    public function getType(): string
    {
        return (string) ($this->data['type'] ?? '');
    }

    public function getOccurrences(): int
    {
        return (int) ($this->data['occurrences'] ?? 0);
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
