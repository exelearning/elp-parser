<?php

/**
 * Diagnostic.php
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

use JsonSerializable;

/**
 * Typed validation diagnostic.
 */
final class Diagnostic implements JsonSerializable
{
    public const ERROR = 'error';
    public const WARNING = 'warning';

    /** @var array<string, mixed> */
    private array $context;

    /**
     * @param string               $severity Diagnostic severity.
     * @param string               $code     Stable diagnostic code.
     * @param string               $message  Human-readable message.
     * @param array<string, mixed> $context  Diagnostic context.
     */
    public function __construct(
        private string $severity,
        private string $code,
        private string $message,
        array $context = []
    ) {
        $this->context = $context;
    }

    /**
     * Build a diagnostic from the existing array contract.
     *
     * @param array<string, mixed> $data     Diagnostic data.
     * @param string               $severity Severity.
     *
     * @return self
     */
    public static function fromArray(array $data, string $severity): self
    {
        $context = $data['context'] ?? [];

        return new self(
            $severity,
            (string) ($data['code'] ?? 'unknown'),
            (string) ($data['message'] ?? ''),
            is_array($context) ? $context : []
        );
    }

    public function getSeverity(): string
    {
        return $this->severity;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    /** @return array<string, mixed> */
    public function getContext(): array
    {
        return $this->context;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'severity' => $this->severity,
            'code' => $this->code,
            'message' => $this->message,
            'context' => $this->context,
        ];
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }
}
