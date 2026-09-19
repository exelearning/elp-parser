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
<a href="https://codecov.io/gh/exelearning/elp-parser"><img src="https://codecov.io/gh/exelearning/elp-parser/graph/badge.svg" alt="Codecov"></a>
</p>

## Features

`ELPParser` supports:

- Legacy `.elp` projects from eXeLearning 2.x based on `contentv3.xml`
- Modern `.elp` / `.elpx` projects based on `content.xml` and ODE 2.0
- eXeLearning 3 and 4 ELPX package conventions without treating them as different XML format versions
- Explicit and heuristic eXeLearning major-version detection with detection details
- Normalized metadata, strings, pages, blocks, iDevices and asset references
- Normalized iDevice state across standard JSON, DataGame, embedded JSON and HTML-only storage patterns
- Asset discovery in HTML, CSS-like values, `srcset` and structured `jsonProperties`
- Archive-backed asset normalization and orphan-asset detection
- Safe ZIP extraction with path traversal and symlink checks
- Configurable limits for entry count, decompressed sizes, XML size and compression ratio
- Streaming extraction to avoid loading large assets into memory
- JSON serialization

For more information, visit the [documentation](https://exelearning.github.io/elp-parser/). The repository also includes an `examples/` directory with executable recipes for inspection, validation, assets, diffs, fingerprints and extraction.

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

## Command-line interface

Composer exposes `vendor/bin/elp-parser` for common inspection and automation workflows:

```bash
vendor/bin/elp-parser inspect course.elpx
vendor/bin/elp-parser validate course.elpx
vendor/bin/elp-parser manifest course.elpx
vendor/bin/elp-parser assets course.elpx
vendor/bin/elp-parser json course.elpx --detailed
vendor/bin/elp-parser diff old.elpx new.elpx
vendor/bin/elp-parser fingerprint course.elpx
vendor/bin/elp-parser extract course.elpx output/
```

Use `--json` with `inspect`, `validate` and `fingerprint` for machine-readable output. See the CLI documentation for exit-code semantics.

## Usage

### Streams and uploads

Projects can be parsed from PHP streams or in-memory bytes. Stream input is copied in chunks to a bounded temporary file because `ZipArchive` requires a filesystem path:

```php
$stream = fopen($_FILES['project']['tmp_name'], 'rb');
$parser = ELPParser::fromStream($stream, 'elpx');

$parserFromBytes = ELPParser::fromContents($bytes, 'elpx');
```

Temporary files owned by parser instances are removed automatically. `inspectStream()` and `inspectContents()` provide the corresponding lightweight inspection APIs.

### Detect and identify packages

Use the lightweight detection helpers before full parsing when the input type is unknown:

```php
if (ELPParser::supports($path)) {
    echo ELPParser::identify($path); // legacy-v2, elpx-v4, ...
    $info = ELPParser::probe($path); // alias of lightweight inspect()
}
```

### Parser options

`ArchiveLimits` remains accepted as the second argument for backward compatibility. New feature switches use `ParserOptions`:

```php
use Exelearning\Archive\ArchiveLimits;
use Exelearning\ParserOptions;

$options = new ParserOptions(
    archiveLimits: new ArchiveLimits(maxXmlBytes: 32 * 1024 * 1024),
    parseAssets: false,
    collectStrings: false,
    normalizeIdeviceState: false
);

$parser = ELPParser::fromFile($path, $options);
```

Disable work only when the corresponding derived data is not needed.

### Lightweight inspection

For cataloging or indexing, `inspect()` reads archive metadata and the project XML without normalizing pages, iDevices or assets:

```php
$info = ELPParser::inspect('/path/to/project.elpx');

echo $info['title'];
echo $info['formatVersion'];
echo $info['packageProfile'];
```

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
echo $parser->getFormatFamily();         // legacy | ode
echo $parser->getFormatVersion();        // null | 2.0
echo $parser->getExeVersion();           // raw upstream version string when present
echo $parser->getApplicationVersion();   // alias with explicit application semantics
echo $parser->getPackageProfile();       // legacy-v2 | elpx-v3 | elpx-v4 | ode-v3 ...
echo $parser->getResourceLayout();       // none | content-resources | legacy-temp-paths | mixed
echo $parser->getResourceProfile();      // v3-uuid-resources | v4-resource-tree | mixed-modern-resources | ...

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

// Modern iDevices expose storagePattern, data and stateDecodeError.
$pageTexts = $parser->getPageTexts();
$assets = $parser->getAssets();
$assetsDetailed = $parser->getAssetsDetailed();
$orphanAssets = $parser->getOrphanAssets();
$missingAssets = $parser->getMissingAssets();
$brokenReferences = $parser->getBrokenReferences();
$internalLinks = $parser->getInternalLinks();
$brokenInternalLinks = $parser->getBrokenInternalLinks();
$manifest = $parser->getPackageManifest();
$metadata = $parser->getMetadata();
$userPreferences = $parser->getUserPreferences();
$odeResources = $parser->getOdeResources();
$odeProperties = $parser->getOdeProperties();
$pageTree = $parser->getPageTree();
```

Direct lookup helpers are also available: `getPageById()`, `getBlockById()`, `getIdeviceById()`, `getProjectId()` and `getProjectVersionId()`.

A parallel typed API is available through `$parser->getProject()`. It returns `Project`, `Page`, `Block`, `Idevice`, `Asset` and `VersionInfo` model objects while the existing array APIs remain unchanged.

Asset references are normalized against the actual ZIP entries. This prevents external URLs and nonexistent paths from being reported as package assets.

### Typed validation and iDevice extensions

The existing validation arrays remain supported. A typed wrapper is available when object APIs are preferable:

```php
$result = $parser->validateResult();

if (!$result->isValid()) {
    foreach ($result->errors() as $diagnostic) {
        echo $diagnostic->getCode() . ': '
            . $diagnostic->getMessage() . PHP_EOL;
    }
}
```

Domain-specific iDevice decoding can be plugged in without modifying the parser:

```php
use Exelearning\Parser\IdeviceDecoderInterface;
use Exelearning\Parser\IdeviceDecoderRegistry;
use Exelearning\ParserOptions;

$registry = new IdeviceDecoderRegistry([
    new MyIdeviceDecoder(),
]);

$options = new ParserOptions(ideviceDecoders: $registry);
$parser = ELPParser::fromFile($path, $options);
```

Custom decoder output is exposed as `customDecoder` and `customData` on matching normalized iDevices.

### Validation

Normal parsing remains tolerant. Validation can be requested explicitly:

```php
$result = $parser->validate();

if (!$result['valid']) {
    print_r($result['errors']);
}

print_r($result['warnings']);
```

The validator reports unresolved assets, broken internal `exe-node:` links, duplicate identifiers, broken page-parent relationships, hierarchy cycles, relationship/order inconsistencies, missing iDevice runtime directories and missing v4 baseline files/directories.

Schema validation is optional and only uses a caller-supplied trusted local schema:

```php
use Exelearning\Validation\SchemaValidator;

$xsdResult = $parser->validateSchema('/trusted/path/ode-content.xsd');
$dtdResult = $parser->validateSchema(
    '/trusted/path/content.dtd',
    SchemaValidator::TYPE_DTD
);
```

Package-supplied DTDs are not trusted for this API. Schema loading uses `LIBXML_NONET`. The optional schema-validation API requires `ext-dom`.

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

### Fingerprints and project diffs

Exact archive bytes and normalized logical content use separate fingerprints:

```php
$archiveHash = $parser->getArchiveFingerprint();
$contentHash = $parser->getContentFingerprint();

$same = $parser->hasSameContentAs($otherParser);
$diff = $parser->diff($otherParser);
```

The normalized content fingerprint ignores volatile package identity/version fields such as `odeId`, `odeVersionId` and the eXeLearning application version. It keeps parsed project structure and includes hashes of project resource bytes. This makes it suitable for change detection without treating ZIP packaging differences as content changes.

The semantic diff reports metadata, page, block, iDevice and resource additions/removals/changes.

### Detailed JSON contract

Detailed exports carry an explicit schema version and ship with a bundled JSON Schema:

```php
echo ELPParser::getDetailedSchemaVersion(); // 1.0

$schema = ELPParser::getDetailedJsonSchema();
$schemaPath = ELPParser::getDetailedJsonSchemaPath();
```

The detailed schema version is independent from the eXeLearning application version, ODE format version and Composer package version.

### Export JSON

```php
$json = $parser->exportJson();
$parser->exportJson('/path/to/output.json');

$detailedJson = $parser->exportDetailedJson();
$detailed = $parser->toDetailedArray();
```

The existing JSON export remains compact. The detailed representation adds format/version information, metadata, ODE preferences/resources/properties, pages and page tree, blocks, iDevices, assets and archive entries.

### Read or stream individual entries

For large assets, use stream access instead of loading the complete entry into memory:

```php
if ($parser->hasEntry('content/resources/video.mp4')) {
    $output = fopen('/tmp/video.mp4', 'wb');

    try {
        $parser->copyEntryToStream(
            'content/resources/video.mp4',
            $output
        );
    } finally {
        fclose($output);
    }
}

$smallFile = $parser->getEntryContents(
    'content/resources/config.json',
    1024 * 1024
);

$parser->extractEntry(
    'content/resources/image.png',
    '/tmp/image.png'
);
```

Per-entry reads and copies honor configured archive limits and reject unsafe ZIP entry paths/symlinks.

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

The parser distinguishes the internal project format from the detected eXeLearning application/package version:

- legacy `contentv3.xml` projects report major version `2`
- eXeLearning 3 and 4 use the same modern ODE `content.xml` format with root `version="2.0"`
- modern packages accept both the historical `eXeVersion` resource key and the current `exe_version` key
- `.elpx` + `content.xml` + a root `content.dtd` remains the current signal for likely v4-style packages when embedded metadata still reports `3.0`
- v3 UUID asset directories and the v4 resource-tree layout are reported separately through `getResourceProfile()`
- multi-digit future major versions such as `10.x` can be parsed from version metadata

Use `getFormatVersion()` for the ODE format version, `getApplicationVersion()` for the declared eXeLearning version, and `getVersionInfo()` when the distinction between declared and inferred versions matters.

## Performance characteristics

Parsed projects are indexed by page, block and iDevice ID for constant-time lookup. Aggregate collections and diagnostics are cached because parser instances are immutable after construction. Asset-reference resolution also caches normalized archive lookups.

The upstream compatibility corpus records per-project timings, total elapsed time, peak memory and the five slowest projects. These measurements are informational and do not impose brittle timing thresholds in CI.

## Test coverage

The test suite enforces a minimum **90% project coverage** locally and in CI. The coverage job produces a Clover report and uploads it to Codecov. Codecov also requires at least 90% project and patch coverage for pull requests.

```bash
composer test:coverage
```

## Compatibility regression testing

The regular test suite includes a deterministic corpus for malformed XML, encoded and Unicode asset paths, malformed iDevice state and cyclic page hierarchies.

A separate `Upstream Compatibility` workflow runs weekly against project fixtures from `exelearning/exelearning`. It discovers ZIP-backed `.elp` / `.elpx` fixtures containing `content.xml` or `contentv3.xml`, compares lightweight inspection with full parsing, and fails on compatibility regressions.

The corpus runner can also be used locally:

```bash
php tests/upstream-compat.php /path/to/exelearning/test/fixtures
```

## Project documentation

- [Getting started](docs/getting-started.md)
- [Supported formats](docs/formats.md)
- [Validation](docs/validation.md)
- [Security](docs/security.md)
- [iDevices](docs/idevices.md)
- [Assets and package entries](docs/assets.md)
- [Performance](docs/performance.md)
- [Cookbook](docs/cookbook.md)
- [Contributing](CONTRIBUTING.md)
- [Upgrading](UPGRADING.md)
- [Security policy](SECURITY.md)
- [Changelog](CHANGELOG.md)

## License

The project is distributed under the MIT License. See [LICENSE.md](LICENSE.md).
