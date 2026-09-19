<?php

/**
 * ValidationResult.php
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
 * Typed wrapper around validation errors and warnings.
 */
final class ValidationResult implements JsonSerializable
{
    /** @var array<int, Diagnostic> */
    private array $errors;

    /** @var array<int, Diagnostic> */
    private array $warnings;

    /**
     * @param array<int, Diagnostic> $errors   Error diagnostics.
     * @param array<int, Diagnostic> $warnings Warning diagnostics.
     */
    public function __construct(array $errors = [], array $warnings = [])
    {
        $this->errors = array_values($errors);
        $this->warnings = array_values($warnings);
    }

    /**
     * Build from the existing validation array contract.
     *
     * @param array<string, mixed> $data Validation result.
     *
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $errors = [];
        foreach (($data['errors'] ?? []) as $diagnostic) {
            if (is_array($diagnostic)) {
                $errors[] = Diagnostic::fromArray(
                    $diagnostic,
                    Diagnostic::ERROR
                );
            }
        }

        $warnings = [];
        foreach (($data['warnings'] ?? []) as $diagnostic) {
            if (is_array($diagnostic)) {
                $warnings[] = Diagnostic::fromArray(
                    $diagnostic,
                    Diagnostic::WARNING
                );
            }
        }

        return new self($errors, $warnings);
    }

    public function isValid(): bool
    {
        return $this->errors === [];
    }

    /** @return array<int, Diagnostic> */
    public function errors(): array
    {
        return $this->errors;
    }

    /** @return array<int, Diagnostic> */
    public function warnings(): array
    {
        return $this->warnings;
    }

    public function has(string $code): bool
    {
        foreach (array_merge($this->errors, $this->warnings) as $diagnostic) {
            if ($diagnostic->getCode() === $code) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'valid' => $this->isValid(),
            'errors' => array_map(
                static fn(Diagnostic $item): array => [
                    'code' => $item->getCode(),
                    'message' => $item->getMessage(),
                    'context' => $item->getContext(),
                ],
                $this->errors
            ),
            'warnings' => array_map(
                static fn(Diagnostic $item): array => [
                    'code' => $item->getCode(),
                    'message' => $item->getMessage(),
                    'context' => $item->getContext(),
                ],
                $this->warnings
            ),
        ];
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }
}
