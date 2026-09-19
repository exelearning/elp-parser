<?php

/**
 * VersionInfo.php
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
 * Typed wrapper around version detection details.
 */
final class VersionInfo implements JsonSerializable
{
    /** @var array<string, mixed> */
    private array $data;

    /** @param array<string, mixed> $data Version information. */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function getDeclaredVersion(): ?string
    {
        $value = $this->data['declared'] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function getDeclaredMajor(): ?int
    {
        $value = $this->data['declaredMajor'] ?? null;

        return is_int($value) ? $value : null;
    }

    public function getDetectedMajor(): int
    {
        return (int) ($this->data['detectedMajor'] ?? 0);
    }

    public function getSource(): string
    {
        return (string) ($this->data['source'] ?? '');
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
