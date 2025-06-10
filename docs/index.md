# ELP Parser Documentation

ELP Parser is a PHP library designed to parse and extract content from ELP (eXe Learning Package) files. It provides a simple and intuitive interface to access metadata and content from ELP packages.

## Features

- Parse ELP files (both version 2 and 3 supported)
- Extract metadata like title, description, author, etc.
- Retrieve a complete metadata tree
- Access learning resource information
- Extract package contents to a specified directory
- JSON serialization support

## Quick Example

```php
<?php
use Exelearning\ElpParser\ElpParser;

// Parse an ELP file
$parser = ElpParser::fromFile('path/to/your/package.elp');

// Get metadata
$title = $parser->getTitle();
$author = $parser->getAuthor();
$description = $parser->getDescription();

// Extract contents
$parser->extract('destination/path');
```

---

## Getting Started

### Installation

Install the ELP Parser via Composer by running the following command in your project directory:

```bash
composer require exelearning/elp-parser
```

### Basic Usage

Here's a simple example to get you started with ELP Parser:

```php
<?php

require 'vendor/autoload.php';

use Exelearning\ElpParser\ElpParser;

// Create a parser instance from an ELP file
$parser = ElpParser::fromFile('path/to/your/package.elp');

// Access basic metadata
echo "Title: " . $parser->getTitle() . "\n";
echo "Author: " . $parser->getAuthor() . "\n";
echo "Description: " . $parser->getDescription() . "\n";

// Get all strings from the package
$strings = $parser->getStrings();

// Extract the package contents
$parser->extract('path/to/destination');
```

### Configuration

No additional configuration is required. The library works out of the box once installed.

### Next Steps

- Explore advanced usage examples below
- Refer to the API Reference for a complete list of available methods

---

## Usage Guide

### Working with ELP Files

#### Parsing an ELP File

To parse an ELP file, use the following code:

```php
use Exelearning\ElpParser\ElpParser;

$parser = ElpParser::fromFile('path/to/package.elp');
```

#### Accessing Metadata

The parser provides several methods to access package metadata:

```php
<?php
// Get basic information
$title = $parser->getTitle();
$description = $parser->getDescription();
$author = $parser->getAuthor();
$license = $parser->getLicense();
$language = $parser->getLanguage();
$resourceType = $parser->getLearningResourceType();

// Get the ELP version
$version = $parser->getVersion();

// Get all strings from the package
$strings = $parser->getStrings();
```

#### Extracting Contents

To extract the contents of an ELP package:

```php
$parser->extract('path/to/destination');
```

#### Converting to Array or JSON

The parser implements `JsonSerializable` and provides methods for data conversion:

```php
<?php
// Get array representation
$data = $parser->toArray();

// Get JSON representation
$json = json_encode($parser);
```

#### Exporting to a JSON file

You can directly export the parsed data to a JSON file using `exportJson()`:

```php
$parser->exportJson('path/to/output.json');

// Obtain a metadata tree
$meta = $parser->getMetadata();
```

### Version Compatibility

The library supports both version 2 and version 3 of ELP files. The parsing process automatically detects the version and handles the content appropriately.

### Error Handling

It's recommended to wrap operations in try-catch blocks to handle potential exceptions:

```php
<?php
try {
    $parser = ElpParser::fromFile('path/to/package.elp');
} catch (\Exception $e) {
    echo "Error parsing ELP file: " . $e->getMessage();
}
```

---

## Requirements

- PHP 8.0 or higher
- SimpleXML extension
- ZipArchive extension

---

## Summary

With ELP Parser, you can efficiently parse, extract, and manipulate the contents of ELP files. The library's flexibility and ease of use make it an excellent choice for working with eXe Learning Packages. Explore more advanced usage and detailed API documentation to harness its full potential.
