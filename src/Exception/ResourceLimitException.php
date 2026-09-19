<?php

/**
 * ResourceLimitException.php
 *
 * PHP Version 8.0
 *
 * @category Parser
 * @package  Exelearning
 * @author   INTEF <cedec@educacion.gob.es>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/exelearning/elp-parser
 */

namespace Exelearning\Exception;

/**
 * Raised when an archive exceeds configured parser resource limits.
 */
class ResourceLimitException extends ElpParserException
{
}
