# API Reference

## `Exelearning\ELPParser`

Parser for eXeLearning `.elp` and `.elpx` project files.

Supported project families:

- Legacy `.elp` packages using `contentv3.xml`
- Modern `.elp` / `.elpx` packages using `content.xml`

### Constructor

#### `__construct(string $filePath)`

Create a new parser instance from a project file path.

### Static Methods

#### `fromFile(string $filePath): ELPParser`

Create a parser instance from a file path.

### Core Metadata

#### `getVersion(): int`

Return the detected eXeLearning major version.

#### `getTitle(): string`

Return the project title.

#### `getDescription(): string`

Return the project description.

#### `getAuthor(): string`

Return the project author.

#### `getLicense(): string`

Return the project license.

#### `getLanguage(): string`

Return the project language.

#### `getLearningResourceType(): string`

Return the learning resource type when present.

### Format Introspection

#### `getSourceExtension(): string`

Return the source file extension, usually `elp` or `elpx`.

#### `getContentFormat(): string`

Return the detected internal project format:

- `legacy-contentv3`
- `ode-content`

#### `getContentFile(): string`

Return the XML entry used by the package:

- `contentv3.xml`
- `content.xml`

#### `getContentSchemaVersion(): ?string`

Return the modern ODE schema version when available.

#### `getExeVersion(): ?string`

Return the raw upstream eXeLearning version string when available.

#### `getResourceLayout(): string`

Return the detected resource layout family:

- `content-resources`
- `legacy-temp-paths`
- `mixed`
- `none`

#### `hasRootDtd(): bool`

Return `true` when the archive contains `content.dtd` at the root.

#### `isLikelyVersion4Package(): bool`

Return `true` when the package matches the current v4 heuristic:

- `.elpx`
- modern `content.xml` / ODE package
- root `content.dtd`

#### `isLegacyFormat(): bool`

Return `true` for legacy `contentv3.xml` projects.

### Parsed Content

#### `getStrings(): array`

Return extracted strings from the project.

#### `getPages(): array`

Return parsed page information, including block and idevice data when available.

#### `getVisiblePages(): array`

Return only visible pages.

#### `getBlocks(): array`

Return all parsed blocks across all pages.

#### `getIdevices(): array`

Return all parsed idevices across all pages.

#### `getPageTexts(): array`

Return grouped text content for each page, including the per-idevice text list and a concatenated page text.

#### `getVisiblePageTexts(): array`

Return grouped text content for visible pages only.

#### `getPageTextById(string $pageId): ?array`

Return grouped text content for a single page, or `null` if the page ID does not exist.

#### `getTeacherOnlyIdevices(): array`

Return idevices marked as teacher-only.

#### `getHiddenIdevices(): array`

Return idevices whose visibility flag is false.

#### `getAssets(): array`

Return referenced asset paths detected in the parsed content.

#### `getAssetsDetailed(): array`

Return detailed asset records including path, type, extension, page origins, idevice origins and occurrence count.

#### `getImages(): array`

Return image asset paths.

#### `getAudioFiles(): array`

Return audio asset paths.

#### `getVideoFiles(): array`

Return video asset paths.

#### `getDocuments(): array`

Return document asset paths.

#### `getOrphanAssets(): array`

Return asset files present in the ZIP archive but not referenced by the parsed content.

#### `getArchiveEntries(): array`

Return the archive entry names inside the package.

#### `getMetadata(): array`

Return normalized metadata for the project.

### Serialization

#### `toArray(): array`

Return a compact array summary with:

- `version`
- `title`
- `description`
- `author`
- `license`
- `language`
- `learningResourceType`
- `strings`

#### `jsonSerialize(): mixed`

Return the value used for JSON serialization.

#### `exportJson(?string $destinationPath = null): string`

Return the JSON representation of the compact summary and optionally write it to disk.

### Extraction

#### `extract(string $destinationPath): void`

Extract the package contents to a directory. Extraction is validated entry by entry to block unsafe ZIP paths.
