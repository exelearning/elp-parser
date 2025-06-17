# eXeLearning .elp Parser for PHP

Simple, fast, and extension-free parser for eXeLearning project files

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

**ELP Parser** provides a simple and intuitive API to parse eXeLearning project files (.elp):

- Parse both version 2 and version 3 .elp files
- Extract text content from XML
- Detect file version
- Extract entire .elp file contents
- Retrieve full metadata tree
- No external extensions required
- Lightweight and easy to use (less than 4 KB footprint library)
- Compatible with PHP 8.0 to PHP 8.5

For more information, visit the [documentation](https://exelearning.github.io/elp-parser/).

## Requirements

- PHP 8.0+
- Composer
- zip extension

## Installation

Install the package via Composer:

```bash
composer require exelearning/elp-parser
```

## Usage

### Basic Parsing

```php
use Exelearning\ELPParser;

try {
    // Parse an .elp file
    $parser = ELPParser::fromFile('/path/to/your/project.elp');
    
    // Get the file version
    $version = $parser->getVersion(); // Returns 2 or 3
    
    // Get metadata fields
    $title = $parser->getTitle();
    $description = $parser->getDescription();
    $author = $parser->getAuthor();
    $license = $parser->getLicense();
    $language = $parser->getLanguage();

    // Get all extracted strings
    $strings = $parser->getStrings();
    
    // Print extracted strings
    foreach ($strings as $string) {
        echo $string . "\n";
    }
} catch (Exception $e) {
    echo "Error parsing ELP file: " . $e->getMessage();
}
```

### File Extraction

```php
use Exelearning\ELPParser;

try {
    $parser = ELPParser::fromFile('/path/to/your/project.elp');
    
    // Extract entire .elp contents to a directory
    $parser->extract('/path/to/destination/folder');
} catch (Exception $e) {
    echo "Error extracting ELP file: " . $e->getMessage();
}
```

### Advanced Usage

```php
// Convert parsed data to array
$data = $parser->toArray();

// JSON serialization
$jsonData = json_encode($parser);

// Export directly to a JSON file
$parser->exportJson('path/to/output.json');

// Retrieve full metadata as array
$meta = $parser->getMetadata();
```

## Error Handling

The parser includes robust error handling:
- Detects invalid .elp files
- Throws exceptions for parsing errors
- Supports both version 2 and 3 file formats

## Performance

- Lightweight implementation
- Minimal memory footprint
- Fast XML parsing using native PHP extensions

## Contributing

Contributions are welcome! Please submit pull requests or open issues on our GitHub repository.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
