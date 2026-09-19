<?php

/**
 * UnsafeArchiveException.php
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
 * Raised when a ZIP entry may escape the intended extraction directory.
 */
class UnsafeArchiveException extends ElpParserException
{
}
