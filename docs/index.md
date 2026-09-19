# ELP Parser Documentation

ELP Parser is a PHP library for reading eXeLearning project packages in legacy `contentv3.xml` and modern ODE `content.xml` formats.

## Highlights

- `.elp` and `.elpx` support
- normalized metadata, page, block and iDevice inspection
- explicit version-detection details through `getVersionInfo()`
- archive-backed asset extraction from HTML and structured iDevice properties
- configurable ZIP/XML resource limits
- traversal and symlink checks during extraction
- streaming extraction for large assets

## Quick example

```php
<?php

require 'vendor/autoload.php';

use Exelearning\ELPParser;

$parser = ELPParser::fromFile('path/to/project.elpx');

echo $parser->getTitle() . PHP_EOL;
echo $parser->getVersion() . PHP_EOL;
print_r($parser->getVersionInfo());

foreach ($parser->getPages() as $page) {
    echo $page['title'] . PHP_EOL;
}
```

## Custom archive limits

```php
use Exelearning\Archive\ArchiveLimits;
use Exelearning\ELPParser;

$limits = new ArchiveLimits(maxXmlBytes: 128 * 1024 * 1024);
$parser = ELPParser::fromFile('path/to/project.elpx', $limits);
```

The parser limits entry count, per-entry size, total uncompressed size, XML size and compression ratio before processing untrusted project packages.

## Asset inspection

```php
$assets = $parser->getAssets();
$images = $parser->getImages();
$audio = $parser->getAudioFiles();
$video = $parser->getVideoFiles();
$documents = $parser->getDocuments();
$detailed = $parser->getAssetsDetailed();
$orphans = $parser->getOrphanAssets();
```

References are resolved against entries actually present in the archive, including references found in HTML, CSS-like values, `srcset` and nested `jsonProperties`.

## Extraction

```php
$parser->extract('path/to/destination');
```

Extraction streams each file and rejects unsafe archive paths, ZIP symlinks, and filesystem paths that resolve outside the destination root.
