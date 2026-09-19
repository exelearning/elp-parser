<?php

/**
 * VersionDetector.php
 *
 * PHP Version 8.0
 *
 * @category Parser
 * @package  Exelearning
 * @author   INTEF <cedec@educacion.gob.es>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/exelearning/elp-parser
 */

namespace Exelearning\Support;

/**
 * Detect eXeLearning major versions while exposing how the result was obtained.
 */
class VersionDetector
{
    /**
     * Detect the major version of a modern ODE package.
     *
     * @param string|null $declaredVersion Raw version declared by the package.
     * @param bool        $likelyVersion4  Whether package format signals indicate v4.
     *
     * @return array{declared:?string,declaredMajor:?int,detectedMajor:int,source:string}
     */
    public function detectModern(?string $declaredVersion, bool $likelyVersion4): array
    {
        $declaredMajor = $this->extractMajor($declaredVersion);

        if ($likelyVersion4 && ($declaredMajor === null || $declaredMajor <= 3)) {
            return [
                'declared' => $declaredVersion,
                'declaredMajor' => $declaredMajor,
                'detectedMajor' => 4,
                'source' => 'heuristic',
            ];
        }

        if ($declaredMajor !== null && $declaredMajor >= 3) {
            return [
                'declared' => $declaredVersion,
                'declaredMajor' => $declaredMajor,
                'detectedMajor' => $declaredMajor,
                'source' => 'metadata',
            ];
        }

        return [
            'declared' => $declaredVersion,
            'declaredMajor' => $declaredMajor,
            'detectedMajor' => 3,
            'source' => 'default',
        ];
    }

    /**
     * Extract a multi-digit major version from common version strings.
     *
     * @param string|null $version Raw version string.
     *
     * @return int|null
     */
    private function extractMajor(?string $version): ?int
    {
        if ($version === null || trim($version) === '') {
            return null;
        }

        if (preg_match('/(?:^|\D)(\d+)(?:\.\d+)(?:\.\d+)?/', $version, $matches) === 1) {
            return (int) $matches[1];
        }

        if (preg_match('/^\s*[vV]?(\d+)\s*$/', $version, $matches) === 1) {
            return (int) $matches[1];
        }

        return null;
    }
}
