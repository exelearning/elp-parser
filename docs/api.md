# API Reference

## ElpParser Class

### Static Methods

#### `fromFile(string $filePath): ElpParser`

Creates a new parser instance from an ELP file.

- Parameters:
  - `$filePath`: string - Path to the ELP file
- Returns: ElpParser
- Throws: Exception if file is invalid or unreadable

### Public Methods

#### `getVersion(): int`

Returns the version of the ELP package.

- Returns: int - Version number (2 or 3)

#### `getStrings(): array`

Returns all strings extracted from the package.

- Returns: array - Array of strings

#### `getTitle(): string`

Returns the package title.

- Returns: string

#### `getDescription(): string`

Returns the package description.

- Returns: string

#### `getAuthor(): string`

Returns the package author.

- Returns: string

#### `getLicense(): string`

Returns the package license.

- Returns: string

#### `getLanguage(): string`

Returns the package language.

- Returns: string

#### `getLearningResourceType(): string`

Returns the learning resource type.

- Returns: string

#### `toArray(): array`

Converts the parser data to an array.

- Returns: array - Array representation of the parser data

#### `extract(string $destinationPath): void`

Extracts the package contents to the specified directory.

- Parameters:
  - `$destinationPath`: string - Destination directory path
- Returns: void
- Throws: Exception if extraction fails
