# Security Policy

## Supported versions

Security fixes are applied to the current supported release line. The PHP compatibility matrix is documented in `composer.json` and CI.

## Reporting a vulnerability

Please report security issues privately through GitHub's security reporting/advisory mechanism for this repository when available. Do not open a public issue for a vulnerability before maintainers have had an opportunity to investigate it.

Useful details include:

- affected library version or commit;
- PHP version;
- minimal malicious or malformed package when safe to share privately;
- expected and observed behavior;
- whether the issue involves ZIP extraction, XML parsing, resource limits, temporary files or path handling.

## Security model

ELP/ELPX files are treated as untrusted input. The parser applies archive-entry, decompression, XML-size, traversal and symlink protections. Optional schema validation only uses caller-supplied trusted local schemas and keeps network access disabled.
