# API Reference

## ELPParser Class

ELPParser class for parsing .elp (eXeLearning) project files. This class provides functionality to parse .elp files, which are ZIP archives containing XML content for eXeLearning projects. It supports both version 2 and 3 formats.

**Namespace:** `Exelearning`

**Implements:** `JsonSerializable`

### Constructor

#### `__construct(string $filePath)`

Create a new ELPParser instance.

- **Parameters:**
  - `$filePath` (string): Path to the .elp file
- **Throws:** `Exception` if file cannot be opened or is invalid
- **Return:** void

### Static Methods

#### `fromFile(string $filePath): ELPParser`

Static method to create an ELPParser from a file path.

- **Parameters:**
  - `$filePath` (string): Path to the .elp file
- **Throws:** `Exception` if file cannot be opened or is invalid
- **Returns:** `ELPParser`

### Public Methods

#### `getVersion(): int`

Get the detected ELP file version.

- **Returns:** int - ELP file version (2 or 3)

#### `getStrings(): array`

Get all extracted strings.

- **Returns:** array - List of extracted strings

#### `getTitle(): string`

Get the title of the ELP content.

- **Returns:** string

#### `getDescription(): string`

Get the description of the ELP content.

- **Returns:** string

#### `getAuthor(): string`

Get the author of the ELP content.

- **Returns:** string

#### `getLicense(): string`

Get the license of the ELP content.

- **Returns:** string

#### `getLanguage(): string`

Get the language of the ELP content.

- **Returns:** string

#### `getLearningResourceType(): string`

Get the learning resource type.

- **Returns:** string

#### `toArray(): array`

Convert parser data to an array.

- **Returns:** array - Array containing:
  - version: int
  - title: string
  - description: string
  - author: string
  - license: string
  - language: string
  - learningResourceType: string
  - strings: array

#### `jsonSerialize(): mixed`

JSON serialization method implementing JsonSerializable interface.

- **Returns:** mixed - Data to be JSON serialized

#### `exportJson(?string $destinationPath = null): string`

Export parsed data to JSON. If a destination path is provided, the JSON will be written to that file.

- **Parameters:**
  - `$destinationPath` (string|null): Optional file path for the JSON output
- **Throws:** `Exception` if the JSON cannot be written
- **Returns:** string - JSON representation of the parsed data

#### `getMetadata(): array`

Return a detailed metadata array containing Package, Dublin Core, LOM and LOM-ES
information together with the page tree.

- **Throws:** `Exception` if the XML cannot be parsed
- **Returns:** array - Metadata and content structure

#### `extract(string $destinationPath): void`

Extract contents of an ELP file to a specified directory.

- **Parameters:**
  - `$destinationPath` (string): Directory to extract contents to
- **Throws:** `Exception` if extraction fails
- **Returns:** void

### Protected Properties

- `$filePath` (string): Path to the .elp file
- `$version` (int): ELP file version (2 or 3)
- `$content` (array): Extracted content and metadata
- `$strings` (array): Raw extracted strings
- `$title` (string): Title of the ELP content
- `$description` (string): Description of the ELP content
- `$author` (string): Author of the ELP content
- `$license` (string): License of the ELP content
- `$language` (string): Language of the ELP content
- `$learningResourceType` (string): Learning resource type
