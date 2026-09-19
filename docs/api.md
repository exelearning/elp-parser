# API Reference

## `Exelearning\ELPParser`

### Construction

#### `__construct(string $filePath, ?ArchiveLimits $limits = null)`

Create and immediately parse an eXeLearning project.

#### `fromFile(string $filePath, ?ArchiveLimits $limits = null): ELPParser`

Factory equivalent to the constructor.

#### `fromStream(mixed $stream, string $extension = 'elpx', ?ArchiveLimits $limits = null): ELPParser`

Parse a readable PHP stream using bounded temporary-file spooling.

#### `fromContents(string $contents, string $extension = 'elpx', ?ArchiveLimits $limits = null): ELPParser`

Parse in-memory project bytes.

#### `inspect(string $filePath, ?ArchiveLimits $limits = null): array`

Read core format/version/project metadata without fully normalizing pages, iDevices or assets.

#### `inspectStream(mixed $stream, string $extension = 'elpx', ?ArchiveLimits $limits = null): array`

Lightweight inspection for stream input.

#### `inspectContents(string $contents, string $extension = 'elpx', ?ArchiveLimits $limits = null): array`

Lightweight inspection for in-memory project bytes.

### Detection and configuration

- `supports(string $filePath, ArchiveLimits|ParserOptions|null $options = null): bool`
- `identify(string $filePath, ArchiveLimits|ParserOptions|null $options = null): string`
- `probe(string $filePath, ArchiveLimits|ParserOptions|null $options = null): array`
- `getOptions(): ParserOptions`

`ParserOptions` groups archive limits with optional expensive derived-data features:

- `parseAssets`
- `collectStrings`
- `normalizeIdeviceState`

Existing calls that pass `ArchiveLimits` directly remain supported.

### Version and format

- `getVersion(): int` — detected major version kept for backward compatibility.
- `getVersionInfo(): array` — declared version, declared major, detected major, detection source and signals.
- `getSourceExtension(): string`
- `getContentFormat(): string`
- `getFormatFamily(): string` — `legacy` or `ode`.
- `getContentFile(): string`
- `getContentSchemaVersion(): ?string`
- `getFormatVersion(): ?string` — internal format version such as ODE `2.0`, independent of eXeLearning 3/4.
- `getExeVersion(): ?string`
- `getApplicationVersion(): ?string` — raw declared eXeLearning application version.
- `getPackageProfile(): string` — compatibility profile such as `legacy-v2`, `elpx-v3` or `elpx-v4`.
- `getResourceLayout(): string`
- `getResourceProfile(): string` — `v3-uuid-resources`, `v4-resource-tree`, `mixed-modern-resources`, `legacy-temp-paths` or `none`.
- `hasRootDtd(): bool`
- `isLikelyVersion4Package(): bool`
- `isLegacyFormat(): bool`

### ODE project data

- `getUserPreferences(): array`
- `getOdeResources(): array`
- `getOdeProperties(): array`
- `getProjectId(): ?string`
- `getProjectVersionId(): ?string`

### Core metadata

- `getTitle(): string`
- `getDescription(): string`
- `getAuthor(): string`
- `getLicense(): string`
- `getLanguage(): string`
- `getLearningResourceType(): string`
- `getMetadata(): array`

### Parsed content

- `getStrings(): array`
- `getPages(): array`
- `getPageTree(): array`
- `getPageById(string $pageId): ?array`
- `getVisiblePages(): array`
- `getBlocks(): array`
- `getBlockById(string $blockId): ?array`
- `getIdevices(): array`
- `getIdeviceById(string $ideviceId): ?array`
- `getPageTexts(): array`
- `getVisiblePageTexts(): array`
- `getPageTextById(string $pageId): ?array`
- `getTeacherOnlyIdevices(): array`
- `getHiddenIdevices(): array`

### Assets

- `getAssets(): array`
- `getAssetsDetailed(): array`
- `getImages(): array`
- `getAudioFiles(): array`
- `getVideoFiles(): array`
- `getDocuments(): array`
- `getOrphanAssets(): array`
- `getMissingAssets(): array`
- `getBrokenReferences(): array`
- `getArchiveEntries(): array`
- `hasEntry(string $entryName): bool`
- `getEntryContents(string $entryName, ?int $maxBytes = null): string`
- `copyEntryToStream(string $entryName, $output, ?int $maxBytes = null): int`
- `extractEntry(string $entryName, string $destinationPath, ?int $maxBytes = null): void`
- `getInternalLinks(): array`
- `getBrokenInternalLinks(): array`
- `getUsedIdeviceTypes(): array`
- `getAvailableIdeviceTypes(): array`
- `getMissingIdeviceRuntimes(): array`
- `getPackageManifest(): array`

Asset references are returned only when they resolve to an entry in the project archive. Both the v3 long form (`{{context_path}}/content/resources/...`) and the v4 form (`{{context_path}}/<exportPath>`) are resolved.

### Typed model API

- `getProject(): Exelearning\\Model\\Project`
- Model wrappers: `Project`, `Page`, `Block`, `Idevice`, `Asset`, `VersionInfo`.
- The typed API is additive; existing array-returning APIs remain supported.

### Fingerprints and comparison

- `getArchiveFingerprint(string $algorithm = 'sha256'): string`
- `getArchiveEntryFingerprint(string $entryName, string $algorithm = 'sha256'): string`
- `getContentFingerprint(string $algorithm = 'sha256'): string`
- `hasSameContentAs(ELPParser $other, string $algorithm = 'sha256'): bool`
- `diff(ELPParser $other, string $algorithm = 'sha256'): array`

Archive fingerprints hash exact ZIP bytes. Content fingerprints normalize logical project data, exclude volatile package identity/version metadata and include project resource hashes.

### Serialization and extraction

- `toArray(): array`
- `toDetailedArray(): array`
- `jsonSerialize(): mixed`
- `exportJson(?string $destinationPath = null): string`
- `exportDetailedJson(?string $destinationPath = null): string`
- `extract(string $destinationPath): void`

## `Exelearning\Archive\ArchiveLimits`

Configures limits applied before and during archive processing:

```php
new ArchiveLimits(
    maxEntries: 20000,
    maxEntryBytes: 1073741824,
    maxTotalBytes: 2147483647,
    maxXmlBytes: 67108864,
    maxCompressionRatio: 1000.0
);
```

## Exceptions

All parser-specific exceptions extend `Exelearning\Exception\ElpParserException`:

- `InvalidArchiveException`
- `InvalidXmlException`
- `UnsupportedFormatException`
- `UnsafeArchiveException`
- `ResourceLimitException`


### Validation

- `validate(): array` — alias of `validatePackage()`.
- `validateResult(): Exelearning\\Validation\\ValidationResult` — typed diagnostics while preserving the array API.
- `validatePackage(): array` — structural/package diagnostics with `valid`, `errors` and `warnings`.
- `validateSchema(string $schemaPath, string $type = 'xsd'): array` — validate the project XML against a caller-supplied trusted local XSD or DTD.

Use `Exelearning\Validation\SchemaValidator::TYPE_XSD` or `TYPE_DTD`. Schema validation is optional and requires the DOM extension.


### Normalized iDevice state

Modern iDevice records include:

- `jsonPropertiesRaw` — unmodified `jsonProperties` payload.
- `storagePattern` — `standard-json`, `data-game`, `embedded-json` or `html-only`.
- `data` — normalized decoded state when available.
- `stateDecodeError` — decoding error text without making project parsing fail.

The existing `html` and decoded `jsonProperties` fields remain available.


### Validation models

- `Exelearning\Validation\Diagnostic`
- `Exelearning\Validation\ValidationResult`

`ValidationResult` provides `isValid()`, `errors()`, `warnings()`, `has()`, `toArray()` and JSON serialization.

### Custom iDevice decoders

Implement `Exelearning\Parser\IdeviceDecoderInterface`, register instances in `IdeviceDecoderRegistry`, then pass the registry through `ParserOptions::$ideviceDecoders`.

The first registered decoder whose `supports()` method returns true supplies `customDecoder` and `customData` fields for the normalized iDevice.
