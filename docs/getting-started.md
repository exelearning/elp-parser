# Getting Started

## Installation

The ELP Parser can be installed via Composer. Run the following command in your project directory:

```bash
composer require agustinx/elp-parser
```

## Basic Usage

Here's a simple example to get you started with ELP Parser:

```php
<?php

require 'vendor/autoload.php';

use Agustinx\ElpParser\ElpParser;

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

## Configuration

No additional configuration is required. The library works out of the box once installed.

## Next Steps

- Check out the [Usage Guide](usage.md) for more detailed examples
- See the [API Reference](api.md) for complete documentation of all available methods
