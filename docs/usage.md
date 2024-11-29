# Usage Guide

## Working with ELP Files

### Parsing an ELP File

```php
use Agustinx\ElpParser\ElpParser;

$parser = ElpParser::fromFile('path/to/package.elp');
```

### Accessing Metadata

The parser provides several methods to access package metadata:

```php
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

### Extracting Contents

To extract the contents of an ELP package:

```php
$parser->extract('path/to/destination');
```

### Converting to Array or JSON

The parser implements JsonSerializable and provides methods for data conversion:

```php
// Get array representation
$data = $parser->toArray();

// Get JSON representation
$json = json_encode($parser);
```

## Version Compatibility

The library supports both version 2 and version 3 of ELP files. The parsing process automatically detects the version and handles the content appropriately.

## Error Handling

It's recommended to wrap operations in try-catch blocks to handle potential exceptions:

```php
try {
    $parser = ElpParser::fromFile('path/to/package.elp');
} catch (\Exception $e) {
    echo "Error parsing ELP file: " . $e->getMessage();
}
```
