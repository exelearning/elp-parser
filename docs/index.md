# ELP Parser Documentation

ELP Parser is a PHP library designed to parse and extract content from ELP (eXe Learning Package) files. It provides a simple and intuitive interface to access metadata and content from ELP packages.

## Features

- Parse ELP files (both version 2 and 3 supported)
- Extract metadata like title, description, author, etc.
- Access learning resource information
- Extract package contents to a specified directory
- JSON serialization support

## Quick Example

```php
use Agustinx\ElpParser\ElpParser;

// Parse an ELP file
$parser = ElpParser::fromFile('path/to/your/package.elp');

// Get metadata
$title = $parser->getTitle();
$author = $parser->getAuthor();
$description = $parser->getDescription();

// Extract contents
$parser->extract('destination/path');
```

## Requirements

- PHP 7.4 or higher
- SimpleXML extension
- ZipArchive extension

## Installation

Install the package via Composer:

```bash
composer require agustinx/elp-parser
```
