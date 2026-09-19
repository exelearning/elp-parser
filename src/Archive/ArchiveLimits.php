<?php

/**
 * ArchiveLimits.php
 *
 * PHP Version 8.0
 *
 * @category Parser
 * @package  Exelearning
 * @author   INTEF <cedec@educacion.gob.es>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/exelearning/elp-parser
 */

namespace Exelearning\Archive;

use InvalidArgumentException;

/**
 * Configurable resource limits used while reading and extracting archives.
 *
 * @category Parser
 * @package  Exelearning
 * @author   INTEF <cedec@educacion.gob.es>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/exelearning/elp-parser
 */
class ArchiveLimits
{
    /**
     * @param int   $maxEntries          Maximum number of ZIP entries.
     * @param int   $maxEntryBytes       Maximum uncompressed bytes per entry.
     * @param int   $maxTotalBytes       Maximum total uncompressed bytes.
     * @param int   $maxXmlBytes         Maximum XML document size.
     * @param float $maxCompressionRatio Maximum accepted compression ratio.
     */
    public function __construct(
        public int $maxEntries = 20000,
        public int $maxEntryBytes = 1073741824,
        public int $maxTotalBytes = 2147483647,
        public int $maxXmlBytes = 67108864,
        public float $maxCompressionRatio = 1000.0
    ) {
        if (
            $this->maxEntries < 1
            || $this->maxEntryBytes < 1
            || $this->maxTotalBytes < 1
            || $this->maxXmlBytes < 1
            || $this->maxCompressionRatio <= 0
        ) {
            throw new InvalidArgumentException('Archive limits must be greater than zero.');
        }
    }
}
