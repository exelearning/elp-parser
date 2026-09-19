# Security

ELP/ELPX files are ZIP containers and should be treated as untrusted input.

The library protects against:

- path traversal and absolute ZIP entry paths;
- ZIP symlink extraction;
- duplicate entries;
- excessive entry count;
- excessive uncompressed entry and total sizes;
- excessive compression ratios;
- oversized XML documents;
- external XML network access;
- extraction through pre-existing symlink targets.

Configure stricter limits for public upload endpoints.

```php
use Exelearning\Archive\ArchiveLimits;
use Exelearning\ELPParser;

$limits = new ArchiveLimits(
    maxEntries: 5000,
    maxEntryBytes: 128 * 1024 * 1024,
    maxTotalBytes: 512 * 1024 * 1024,
    maxXmlBytes: 16 * 1024 * 1024,
    maxCompressionRatio: 250.0
);

$parser = ELPParser::fromFile($path, $limits);
```

See the repository `SECURITY.md` for vulnerability reporting.
