# API Reference

## `Exelearning\ELPParser`

### Construction

#### `__construct(string $filePath, ?ArchiveLimits $limits = null)`

Create and immediately parse an eXeLearning project.

#### `fromFile(string $filePath, ?ArchiveLimits $limits = null): ELPParser`

Factory equivalent to the constructor.

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
- `getVisiblePages(): array`
- `getBlocks(): array`
- `getIdevices(): array`
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
- `getArchiveEntries(): array`

Asset references are returned only when they resolve to an entry in the project archive. Both the v3 long form (`{{context_path}}/content/resources/...`) and the v4 form (`{{context_path}}/<exportPath>`) are resolved.

### Serialization and extraction

- `toArray(): array`
- `jsonSerialize(): mixed`
- `exportJson(?string $destinationPath = null): string`
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
