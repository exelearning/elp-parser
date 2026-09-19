<?php

/**
 * ParserOptions.php
 *
 * PHP Version 8.0
 *
 * @category Parser
 * @package  Exelearning
 * @author   INTEF <cedec@educacion.gob.es>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/exelearning/elp-parser
 */

namespace Exelearning;

use Exelearning\Archive\ArchiveLimits;

/**
 * Parser feature and resource-limit options.
 */
final class ParserOptions
{
    public ArchiveLimits $archiveLimits;
    public bool $parseAssets;
    public bool $collectStrings;
    public bool $normalizeIdeviceState;

    /**
     * @param ArchiveLimits|null $archiveLimits         Archive safety limits.
     * @param bool               $parseAssets           Resolve referenced assets during parsing.
     * @param bool               $collectStrings        Build the normalized strings collection.
     * @param bool               $normalizeIdeviceState Decode modern iDevice state payloads.
     */
    public function __construct(
        ?ArchiveLimits $archiveLimits = null,
        bool $parseAssets = true,
        bool $collectStrings = true,
        bool $normalizeIdeviceState = true
    ) {
        $this->archiveLimits = $archiveLimits ?? new ArchiveLimits();
        $this->parseAssets = $parseAssets;
        $this->collectStrings = $collectStrings;
        $this->normalizeIdeviceState = $normalizeIdeviceState;
    }
}
