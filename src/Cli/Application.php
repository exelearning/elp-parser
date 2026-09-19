<?php

/**
 * Application.php
 *
 * PHP Version 8.0
 *
 * @category CLI
 * @package  Exelearning
 * @author   INTEF <cedec@educacion.gob.es>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/exelearning/elp-parser
 */

namespace Exelearning\Cli;

use Exelearning\ELPParser;
use Throwable;

/**
 * Dependency-free command-line interface for common parser workflows.
 */
final class Application
{
    /**
     * Run the CLI application.
     *
     * @param array<int, string> $argv   Command-line arguments.
     * @param resource|null      $stdout Output stream.
     * @param resource|null      $stderr Error stream.
     *
     * @return int Process exit code.
     */
    public function run(
        array $argv,
        $stdout = null,
        $stderr = null
    ): int {
        $stdout = $stdout ?? fopen('php://stdout', 'wb');
        $stderr = $stderr ?? fopen('php://stderr', 'wb');

        if (!is_resource($stdout) || !is_resource($stderr)) {
            return 2;
        }

        $arguments = array_values(array_slice($argv, 1));
        $json = $this->removeFlag($arguments, '--json');
        $detailed = $this->removeFlag($arguments, '--detailed');
        $command = array_shift($arguments) ?? 'help';

        try {
            return match ($command) {
                'help', '--help', '-h' => $this->help($stdout),
                'inspect' => $this->inspect($arguments, $json, $stdout, $stderr),
                'validate' => $this->validate($arguments, $json, $stdout, $stderr),
                'manifest' => $this->manifest($arguments, $stdout, $stderr),
                'assets' => $this->assets($arguments, $stdout, $stderr),
                'json' => $this->json($arguments, $detailed, $stdout, $stderr),
                'diff' => $this->diff($arguments, $stdout, $stderr),
                'fingerprint' => $this->fingerprint($arguments, $json, $stdout, $stderr),
                'extract' => $this->extract($arguments, $stdout, $stderr),
                default => $this->unknownCommand($command, $stderr),
            };
        } catch (Throwable $exception) {
            $this->write($stderr, 'Error: ' . $exception->getMessage() . PHP_EOL);

            return 1;
        }
    }

    /**
     * Show CLI usage.
     *
     * @param resource $stdout Output stream.
     *
     * @return int
     */
    private function help($stdout): int
    {
        $this->write(
            $stdout,
            <<<'TXT'
ELP Parser CLI

Usage:
  elp-parser inspect <file> [--json]
  elp-parser validate <file> [--json]
  elp-parser manifest <file>
  elp-parser assets <file>
  elp-parser json <file> [--detailed]
  elp-parser diff <left> <right>
  elp-parser fingerprint <file> [--json]
  elp-parser extract <file> <destination>
  elp-parser help

Exit codes:
  0  command succeeded / validation passed / projects have no semantic diff
  1  parser error / validation failed / projects differ
  2  invalid CLI usage

TXT
        );

        return 0;
    }

    /**
     * Inspect lightweight project metadata.
     *
     * @param array<int, string> $arguments Command arguments.
     * @param bool               $json      Output JSON.
     * @param resource           $stdout    Output stream.
     * @param resource           $stderr    Error stream.
     *
     * @return int
     */
    private function inspect(
        array $arguments,
        bool $json,
        $stdout,
        $stderr
    ): int {
        $path = $this->requiredArgument($arguments, 0, 'inspect requires <file>.', $stderr);
        if ($path === null) {
            return 2;
        }

        $info = ELPParser::inspect($path);

        if ($json) {
            $this->writeJson($stdout, $info);
            return 0;
        }

        $this->write($stdout, 'Title: ' . ($info['title'] ?? '') . PHP_EOL);
        $this->write($stdout, 'Profile: ' . ($info['packageProfile'] ?? '') . PHP_EOL);
        $this->write($stdout, 'Format: ' . ($info['formatFamily'] ?? '') . PHP_EOL);
        $this->write($stdout, 'Format version: ' . ($info['formatVersion'] ?? '') . PHP_EOL);

        return 0;
    }

    /**
     * Validate a project.
     *
     * @param array<int, string> $arguments Command arguments.
     * @param bool               $json      Output JSON.
     * @param resource           $stdout    Output stream.
     * @param resource           $stderr    Error stream.
     *
     * @return int
     */
    private function validate(
        array $arguments,
        bool $json,
        $stdout,
        $stderr
    ): int {
        $path = $this->requiredArgument($arguments, 0, 'validate requires <file>.', $stderr);
        if ($path === null) {
            return 2;
        }

        $result = ELPParser::fromFile($path)->validate();

        if ($json) {
            $this->writeJson($stdout, $result);
        } else {
            $this->write(
                $stdout,
                ($result['valid'] ? 'VALID' : 'INVALID') . PHP_EOL
            );

            foreach ($result['errors'] as $diagnostic) {
                $this->write(
                    $stdout,
                    'ERROR ' . ($diagnostic['code'] ?? 'unknown') . ': '
                    . ($diagnostic['message'] ?? '') . PHP_EOL
                );
            }

            foreach ($result['warnings'] as $diagnostic) {
                $this->write(
                    $stdout,
                    'WARN ' . ($diagnostic['code'] ?? 'unknown') . ': '
                    . ($diagnostic['message'] ?? '') . PHP_EOL
                );
            }
        }

        return $result['valid'] ? 0 : 1;
    }

    /**
     * Output the package manifest as JSON.
     *
     * @param array<int, string> $arguments Command arguments.
     * @param resource           $stdout    Output stream.
     * @param resource           $stderr    Error stream.
     *
     * @return int
     */
    private function manifest(array $arguments, $stdout, $stderr): int
    {
        $path = $this->requiredArgument($arguments, 0, 'manifest requires <file>.', $stderr);
        if ($path === null) {
            return 2;
        }

        $this->writeJson(
            $stdout,
            ELPParser::fromFile($path)->getPackageManifest()
        );

        return 0;
    }

    /**
     * Output referenced assets as JSON.
     *
     * @param array<int, string> $arguments Command arguments.
     * @param resource           $stdout    Output stream.
     * @param resource           $stderr    Error stream.
     *
     * @return int
     */
    private function assets(array $arguments, $stdout, $stderr): int
    {
        $path = $this->requiredArgument($arguments, 0, 'assets requires <file>.', $stderr);
        if ($path === null) {
            return 2;
        }

        $this->writeJson(
            $stdout,
            ELPParser::fromFile($path)->getAssetsDetailed()
        );

        return 0;
    }

    /**
     * Output parser JSON.
     *
     * @param array<int, string> $arguments Command arguments.
     * @param bool               $detailed  Use detailed representation.
     * @param resource           $stdout    Output stream.
     * @param resource           $stderr    Error stream.
     *
     * @return int
     */
    private function json(
        array $arguments,
        bool $detailed,
        $stdout,
        $stderr
    ): int {
        $path = $this->requiredArgument($arguments, 0, 'json requires <file>.', $stderr);
        if ($path === null) {
            return 2;
        }

        $parser = ELPParser::fromFile($path);
        $this->write(
            $stdout,
            ($detailed ? $parser->exportDetailedJson() : $parser->exportJson())
            . PHP_EOL
        );

        return 0;
    }

    /**
     * Compare two projects semantically.
     *
     * @param array<int, string> $arguments Command arguments.
     * @param resource           $stdout    Output stream.
     * @param resource           $stderr    Error stream.
     *
     * @return int
     */
    private function diff(array $arguments, $stdout, $stderr): int
    {
        $left = $this->requiredArgument($arguments, 0, 'diff requires <left> <right>.', $stderr);
        $right = $this->requiredArgument($arguments, 1, 'diff requires <left> <right>.', $stderr);

        if ($left === null || $right === null) {
            return 2;
        }

        $result = ELPParser::fromFile($left)->diff(
            ELPParser::fromFile($right)
        );
        $this->writeJson($stdout, $result);

        return $result['changed'] ? 1 : 0;
    }

    /**
     * Output exact and normalized fingerprints.
     *
     * @param array<int, string> $arguments Command arguments.
     * @param bool               $json      Output JSON.
     * @param resource           $stdout    Output stream.
     * @param resource           $stderr    Error stream.
     *
     * @return int
     */
    private function fingerprint(
        array $arguments,
        bool $json,
        $stdout,
        $stderr
    ): int {
        $path = $this->requiredArgument($arguments, 0, 'fingerprint requires <file>.', $stderr);
        if ($path === null) {
            return 2;
        }

        $parser = ELPParser::fromFile($path);
        $fingerprints = [
            'archive' => $parser->getArchiveFingerprint(),
            'content' => $parser->getContentFingerprint(),
        ];

        if ($json) {
            $this->writeJson($stdout, $fingerprints);
        } else {
            $this->write($stdout, 'Archive: ' . $fingerprints['archive'] . PHP_EOL);
            $this->write($stdout, 'Content: ' . $fingerprints['content'] . PHP_EOL);
        }

        return 0;
    }

    /**
     * Extract a project.
     *
     * @param array<int, string> $arguments Command arguments.
     * @param resource           $stdout    Output stream.
     * @param resource           $stderr    Error stream.
     *
     * @return int
     */
    private function extract(array $arguments, $stdout, $stderr): int
    {
        $path = $this->requiredArgument($arguments, 0, 'extract requires <file> <destination>.', $stderr);
        $destination = $this->requiredArgument(
            $arguments,
            1,
            'extract requires <file> <destination>.',
            $stderr
        );

        if ($path === null || $destination === null) {
            return 2;
        }

        ELPParser::fromFile($path)->extract($destination);
        $this->write($stdout, 'Extracted to ' . $destination . PHP_EOL);

        return 0;
    }

    /**
     * Handle unknown commands.
     *
     * @param string   $command Unknown command.
     * @param resource $stderr Error stream.
     *
     * @return int
     */
    private function unknownCommand(string $command, $stderr): int
    {
        $this->write($stderr, 'Unknown command: ' . $command . PHP_EOL);
        $this->write($stderr, 'Run "elp-parser help" for usage.' . PHP_EOL);

        return 2;
    }

    /**
     * Return a required positional argument.
     *
     * @param array<int, string> $arguments Arguments.
     * @param int                $index     Argument index.
     * @param string             $message   Error message.
     * @param resource           $stderr    Error stream.
     *
     * @return string|null
     */
    private function requiredArgument(
        array $arguments,
        int $index,
        string $message,
        $stderr
    ): ?string {
        $value = $arguments[$index] ?? null;

        if ($value === null || $value === '') {
            $this->write($stderr, $message . PHP_EOL);
            return null;
        }

        return $value;
    }

    /**
     * Remove a boolean flag from arguments.
     *
     * @param array<int, string> $arguments Arguments.
     * @param string             $flag      Flag to remove.
     *
     * @return bool
     */
    private function removeFlag(array &$arguments, string $flag): bool
    {
        $index = array_search($flag, $arguments, true);

        if ($index === false) {
            return false;
        }

        unset($arguments[$index]);
        $arguments = array_values($arguments);

        return true;
    }

    /**
     * Write pretty JSON.
     *
     * @param resource $stream Output stream.
     * @param mixed    $value  Value to encode.
     *
     * @return void
     */
    private function writeJson($stream, mixed $value): void
    {
        $json = json_encode(
            $value,
            JSON_PRETTY_PRINT
            | JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_THROW_ON_ERROR
        );

        $this->write($stream, $json . PHP_EOL);
    }

    /**
     * Write to a stream.
     *
     * @param resource $stream Output stream.
     * @param string   $text   Text to write.
     *
     * @return void
     */
    private function write($stream, string $text): void
    {
        fwrite($stream, $text);
    }
}
