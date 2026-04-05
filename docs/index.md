# ELP Parser Documentation

ELP Parser is a PHP library for reading eXeLearning project packages in both legacy and modern formats.

## Supported Formats

The library follows the format split documented in the upstream eXeLearning project:

- Legacy `.elp` packages from eXeLearning 2.x use `contentv3.xml`
- Modern `.elpx` packages from eXeLearning 3+ use `content.xml` with the ODE 2.0 model
- Some modern `.elp` packages also use `content.xml`, so the parser detects the internal format instead of relying on the extension alone

## Features

- Parse `.elp` and `.elpx`
- Detect legacy `contentv3.xml` vs modern `content.xml`
- Detect eXeLearning major version when exposed by the package metadata
- Detect likely v4-style `.elpx` packages using root `content.dtd`
- Read title, description, author, license, language and learning resource type
- Retrieve normalized metadata
- Enumerate pages, idevices and asset references
- Extract package contents safely
- Export parsed summaries as JSON

## Quick Example

```php
<?php

require 'vendor/autoload.php';

use Exelearning\ELPParser;

$parser = ELPParser::fromFile('path/to/project.elpx');

echo $parser->getTitle() . PHP_EOL;
echo $parser->getVersion() . PHP_EOL;
echo $parser->getContentFormat() . PHP_EOL;
echo $parser->getResourceLayout() . PHP_EOL;

foreach ($parser->getPages() as $page) {
    echo $page['title'] . PHP_EOL;
}
```

## Installation

```bash
composer require exelearning/elp-parser
```

## Basic Usage

```php
use Exelearning\ELPParser;

$parser = ELPParser::fromFile('path/to/project.elp');

echo "Title: " . $parser->getTitle() . "\n";
echo "Author: " . $parser->getAuthor() . "\n";
echo "Description: " . $parser->getDescription() . "\n";
```

## Format Inspection

```php
echo $parser->getSourceExtension() . "\n";
echo $parser->getContentFile() . "\n";
echo $parser->getContentFormat() . "\n";
echo $parser->getContentSchemaVersion() . "\n";
echo $parser->getExeVersion() . "\n";
echo $parser->getResourceLayout() . "\n";
var_dump($parser->hasRootDtd());
var_dump($parser->isLikelyVersion4Package());
```

## Pages, Strings and Assets

```php
$strings = $parser->getStrings();
$pages = $parser->getPages();
$visiblePages = $parser->getVisiblePages();
$blocks = $parser->getBlocks();
$idevices = $parser->getIdevices();
$pageTexts = $parser->getPageTexts();
$visiblePageTexts = $parser->getVisiblePageTexts();
$pageText = $parser->getPageTextById($pages[0]['id']);
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

In modern ODE-based projects, referenced media commonly appears under `content/resources/...`.
Legacy packages and some older exports may still reference resource paths closer to `files/tmp/...`.
The parser classifies this through `getResourceLayout()`.

## Extraction

```php
$parser->extract('path/to/destination');
```

## Version Notes

- `getVersion()` reports the detected eXeLearning major version
- `getContentFormat()` reports the internal package model
- This lets the library handle eXeLearning 2.x and modern ODE-based packages through a single API
- Newer builds may still expose `exe_version=3.0`, so exact major detection is not always possible from metadata alone
- The library therefore uses a heuristic for likely v4-style packages: `.elpx` + modern ODE layout + root `content.dtd`
