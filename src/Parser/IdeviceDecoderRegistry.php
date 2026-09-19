<?php

/**
 * IdeviceDecoderRegistry.php
 *
 * PHP Version 8.0
 *
 * @category Parser
 * @package  Exelearning
 * @author   INTEF <cedec@educacion.gob.es>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/exelearning/elp-parser
 */

namespace Exelearning\Parser;

/**
 * Ordered registry of optional domain-specific iDevice decoders.
 */
final class IdeviceDecoderRegistry
{
    /** @var array<int, IdeviceDecoderInterface> */
    private array $decoders = [];

    /**
     * @param array<int, IdeviceDecoderInterface> $decoders Initial decoders.
     */
    public function __construct(array $decoders = [])
    {
        foreach ($decoders as $decoder) {
            $this->register($decoder);
        }
    }

    public function register(IdeviceDecoderInterface $decoder): self
    {
        $this->decoders[] = $decoder;

        return $this;
    }

    /** @return array<int, IdeviceDecoderInterface> */
    public function all(): array
    {
        return $this->decoders;
    }

    /**
     * Decode with the first supporting decoder.
     *
     * @param array<string, mixed> $idevice Normalized iDevice.
     *
     * @return array{decoder:string,data:mixed}|null
     */
    public function decode(array $idevice): ?array
    {
        foreach ($this->decoders as $decoder) {
            if (!$decoder->supports($idevice)) {
                continue;
            }

            return [
                'decoder' => get_class($decoder),
                'data' => $decoder->decode($idevice),
            ];
        }

        return null;
    }
}
