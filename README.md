# eXeLearning `.elp` / `.elpx` Parser for PHP

Parser for eXeLearning project files with support for legacy `contentv3.xml` projects and modern ODE `content.xml` packages.

<p align="center">
    <a href="#features">Features</a> |
    <a href="#installation">Installation</a> |
    <a href="#usage">Usage</a>
</p>

<p align="center">
<a href="https://packagist.org/packages/exelearning/elp-parser"><img src="https://img.shields.io/packagist/v/exelearning/elp-parser.svg?style=flat-square" alt="Packagist"></a>
<a href="https://packagist.org/packages/exelearning/elp-parser"><img src="https://img.shields.io/packagist/dm/exelearning/elp-parser.svg?style=flat-square" alt="Packagist"></a>
<a href="https://packagist.org/packages/exelearning/elp-parser"><img src="https://img.shields.io/packagist/php-v/exelearning/elp-parser.svg?style=flat-square" alt="PHP from Packagist"></a>
</p>

## Features

`ELPParser` supports:

- Legacy `.elp` projects from eXeLearning 2.x based on `contentv3.xml`
- Modern `.elp` / `.elpx` projects based on `content.xml` and ODE 2.0
- Explicit and heuristic eXeLearning major-version detection with detection details
- Normalized metadata, strings, pages, blocks, iDevices and asset references
- Asset discovery in HTML, CSS-like values, `srcset` and structured `jsonProperties`
- Archive-backed asset normalization and orphan-asset detection
- Safe ZIP extraction with path traversal and symlink checks
- Configurable limits for entry count, decompressed sizes, XML size and compression ratio
- Streaming extraction to avoid loading large assets into memory
- JSON serialization

For more information, visit the [documentation](https://exelearning.github.io/elp-parser/).

## Requirements

- PHP 8.0+
- Composer
- `ext-zip`
- `ext-simplexml`

Composer declares these extensions and will report a missing requirement during installation.

## Installation

```bash
composer require exelearning/elp-parser
```

## Usage

### Basic parsing

```php
use Exelearning\ELPParser;

$parser = ELPParser::fromFile('/path/to/project.elpx');

echo $parser->getTitle();
echo $parser->getVersion();

foreach ($parser->getStrings() as $string) {
    echo $string . "\n";
}
```

### Format and version inspection

```php
echo $parser->getSourceExtension();      // elp | elpx
echo $parser->getContentFormat();        // legacy-contentv3 | ode-content
echo $parser->getContentFile();          // contentv3.xml | content.xml
echo $parser->getContentSchemaVersion(); // 2.0 for modern ODE packages
echo $parser->getExeVersion();           // raw upstream version string when present
echo $parser->getResourceLayout();       // none | content-resources | legacy-temp-paths | mixed

$versionInfo = $parser->getVersionInfo();
// declared, declaredMajor, detectedMajor, source, signals
```

`getVersion()` remains the compatibility API for the detected major version. `getVersionInfo()` makes it explicit whether that result came from package metadata, the package format, a heuristic, or a default.

### Pages and assets

```php
$pages = $parser->getPages();
$visiblePages = $parser->getVisiblePages();
$blocks = $parser->getBlocks();
$idevices = $parser->getIdevices();
$pageTexts = $parser->getPageTexts();
$assets = $parser->getAssets();
$assetsDetailed = $parser->getAssetsDetailed();
$orphanAssets = $parser->getOrphanAssets();
$metadata = $parser->getMetadata();
```

Asset references are normalized against the actual ZIP entries. This prevents external URLs and nonexistent paths from being reported as package assets.

### Archive limits

Default limits are intentionally generous but bounded. They can be overridden for trusted or unusually large packages:

```php
use Exelearning\Archive\ArchiveLimits;
use Exelearning\ELPParser;

$limits = new ArchiveLimits(
    maxEntries: 30000,
    maxEntryBytes: 1073741824,
    maxTotalBytes: 2147483647,
    maxXmlBytes: 134217728,
    maxCompressionRatio: 1000.0
);

$parser = ELPParser::fromFile('/path/to/project.elpx', $limits);
```

The defaults are 20,000 entries, 1 GiB per entry, approximately 2 GiB total uncompressed data, 64 MiB for the project XML, and a maximum compression ratio of 1000:1.

### Export JSON

```php
$json = $parser->exportJson();
$parser->exportJson('/path/to/output.json');
```

### Extract project files

```php
$parser->extract('/path/to/destination');
```

Extraction is streamed entry by entry. Unsafe paths, ZIP symlinks and extraction targets that resolve outside the destination root are rejected.

## Error handling

Parser errors derive from `Exelearning\Exception\ElpParserException`. More specific exceptions include:

- `InvalidArchiveException`
- `InvalidXmlException`
- `UnsupportedFormatException`
- `UnsafeArchiveException`
- `ResourceLimitException`

```php
use Exelearning\ELPParser;
use Exelearning\Exception\ElpParserException;

try {
    $parser = ELPParser::fromFile('/path/to/project.elpx');
} catch (ElpParserException $exception) {
    echo $exception->getMessage();
}
```

## Version compatibility

The parser distinguishes the internal project format from the detected eXeLearning version:

- legacy `contentv3.xml` projects report major version `2`
- modern ODE packages use declared metadata when it is reliable
- `.elpx` + `content.xml` + a root `content.dtd` remains the current signal for likely v4-style packages when embedded metadata still reports `3.0`
- multi-digit future major versions such as `10.x` can be parsed from version metadata

Use `getVersionInfo()` when the distinction between declared and inferred versions matters.

## License

The project is distributed under the MIT License. See [LICENSE.md](LICENSE.md).
