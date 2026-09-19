<?php

/**
 * Idevice.php
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
 * Typed read-only-style wrapper around parsed iDevice data.
 */
final class Idevice implements JsonSerializable
{
    /** @var array<string, mixed> */
    private array $data;

    /**
     * @param array<string, mixed> $data Parsed iDevice data.
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function getId(): string
    {
        return (string) ($this->data['id'] ?? '');
    }

    public function getType(): string
    {
        return (string) ($this->data['type'] ?? '');
    }

    public function getStoragePattern(): string
    {
        return (string) ($this->data['storagePattern'] ?? '');
    }

    public function getState(): mixed
    {
        return $this->data['data'] ?? [];
    }

    public function getStateDecodeError(): ?string
    {
        $error = $this->data['stateDecodeError'] ?? null;

        return is_string($error) && $error !== '' ? $error : null;
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
