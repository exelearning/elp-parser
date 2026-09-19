# Getting started

## Install

```bash
composer require exelearning/elp-parser
```

The library requires PHP 8.0+, `ext-simplexml` and `ext-zip`. The optional schema-validation API also uses `ext-dom`.

## Parse a project

```php
<?php

require 'vendor/autoload.php';

use Exelearning\ELPParser;

$parser = ELPParser::fromFile('course.elpx');

echo $parser->getTitle() . PHP_EOL;
echo $parser->getPackageProfile() . PHP_EOL;

foreach ($parser->getPages() as $page) {
    echo $page['title'] . PHP_EOL;
}
```

## Inspect without full normalization

Use `inspect()` for cataloging/indexing when pages, iDevices and asset resolution are unnecessary.

```php
$info = ELPParser::inspect('course.elpx');

print_r($info);
```

## Streams and uploads

```php
$stream = fopen('/path/to/upload.elpx', 'rb');
$parser = ELPParser::fromStream($stream, 'elpx');
fclose($stream);
```

See the cookbook for validation, resource extraction, fingerprints, diffs and framework upload recipes.
