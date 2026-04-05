# eXeLearning `.elp` / `.elpx` Parser for PHP

Simple parser for eXeLearning project files.

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

`ELPParser` supports the two eXeLearning project families described in the upstream format docs:

- Legacy `.elp` projects from eXeLearning 2.x based on `contentv3.xml`
- Modern `.elpx` projects from eXeLearning 3+ based on `content.xml` and ODE 2.0
- Modern `.elp` exports that also use `content.xml`
- Detection of eXeLearning major version when the package exposes it
- Heuristic detection of likely v4-style `.elpx` packages using root `content.dtd`
- Extraction of normalized metadata, strings, pages, idevices and asset references
- Safe archive extraction with ZIP path traversal checks
- JSON serialization support

For more information, visit the [documentation](https://exelearning.github.io/elp-parser/).

## Requirements

- PHP 8.0+
- Composer
- `zip` extension
- `simplexml` extension

## Installation

```bash
composer require exelearning/elp-parser
```

## Usage

### Basic Parsing

```php
use Exelearning\ELPParser;

try {
    $parser = ELPParser::fromFile('/path/to/project.elpx');

    $version = $parser->getVersion();
    $title = $parser->getTitle();
    $description = $parser->getDescription();
    $author = $parser->getAuthor();
    $license = $parser->getLicense();
    $language = $parser->getLanguage();

    foreach ($parser->getStrings() as $string) {
        echo $string . "\n";
    }
} catch (Exception $e) {
    echo "Error parsing project: " . $e->getMessage();
}
```

### Format Inspection

```php
use Exelearning\ELPParser;

$parser = ELPParser::fromFile('/path/to/project.elpx');

echo $parser->getSourceExtension();      // elp | elpx
echo $parser->getContentFormat();        // legacy-contentv3 | ode-content
echo $parser->getContentFile();          // contentv3.xml | content.xml
echo $parser->getContentSchemaVersion(); // 2.0 for modern ODE packages
echo $parser->getExeVersion();           // raw upstream version string when present
echo $parser->getResourceLayout();       // none | content-resources | legacy-temp-paths | mixed
var_dump($parser->hasRootDtd());         // true when content.dtd exists at archive root
var_dump($parser->isLikelyVersion4Package());
```

### Pages and Assets

```php
$pages = $parser->getPages();
$visiblePages = $parser->getVisiblePages();
$blocks = $parser->getBlocks();
$idevices = $parser->getIdevices();
$pageTexts = $parser->getPageTexts();
$visiblePageTexts = $parser->getVisiblePageTexts();
$firstPageText = $parser->getPageTextById($pages[0]['id']);
$teacherOnlyIdevices = $parser->getTeacherOnlyIdevices();
$hiddenIdevices = $parser->getHiddenIdevices();
$assets = $parser->getAssets();
$images = $parser->getImages();
$audioFiles = $parser->getAudioFiles();
$videoFiles = $parser->getVideoFiles();
$documents = $parser->getDocuments();
$assetsDetailed = $parser->getAssetsDetailed();
$orphanAssets = $parser->getOrphanAssets();
$metadata = $parser->getMetadata();
```

In modern `content.xml` packages, assets usually live under paths such as `content/resources/...`.
Older projects and some transitional exports may still reference legacy layouts such as `files/tmp/...`.
The parser exposes this through `getResourceLayout()`.

### Export JSON

```php
$json = $parser->exportJson();
$parser->exportJson('/path/to/output.json');
```

### Extract Project Files

```php
$parser->extract('/path/to/destination');
```

## Version Compatibility

The parser distinguishes between project format and eXeLearning version:

- `getContentFormat()` tells you whether the package uses legacy `contentv3.xml` or modern `content.xml`
- `getVersion()` reports the detected eXeLearning major version
- In practice this means:
  - eXeLearning 2.x legacy `.elp` => version `2`
  - modern ODE-based `.elp` => usually version `3`
  - `.elpx` packages with root `content.dtd` are treated as likely v4-style packages and currently report version `4`
  - otherwise modern ODE-based packages default to version `3`

This distinction matters because some projects created with newer eXeLearning builds still identify themselves internally with `exe_version=3.0`, so strict `v4` detection is not always possible from the package alone.
For that reason, the library combines explicit metadata with format heuristics:

- `.elpx`
- `content.xml`
- root `content.dtd`
- optionally `content/resources/...` as the modern resource layout

## Error Handling

The parser throws exceptions for:

- Missing files
- Invalid ZIP archives
- Unsupported project layouts
- XML parsing failures
- Unsafe archive entries during extraction

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
