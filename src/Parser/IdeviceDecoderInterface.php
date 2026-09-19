<?php

/**
 * IdeviceDecoderInterface.php
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
 * Extension point for domain-specific iDevice decoding.
 */
interface IdeviceDecoderInterface
{
    /**
     * Determine whether this decoder supports a normalized iDevice.
     *
     * @param array<string, mixed> $idevice Normalized iDevice.
     *
     * @return bool
     */
    public function supports(array $idevice): bool;

    /**
     * Decode domain-specific iDevice data.
     *
     * @param array<string, mixed> $idevice Normalized iDevice.
     *
     * @return mixed
     */
    public function decode(array $idevice): mixed;
}
