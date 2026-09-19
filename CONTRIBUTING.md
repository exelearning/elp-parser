# Contributing

Contributions are welcome. Keep changes focused, backward compatible where possible, and covered by tests.

## Development setup

```bash
composer install
composer test
composer lint
composer analyse
composer test:coverage
```

The supported PHP matrix is 8.0 through 8.5. CI must remain green on all supported versions.

## Pull requests

- Create a focused branch from `main`.
- Add or update tests for behavior changes.
- Keep public API changes additive unless a breaking change is explicitly planned for a major release.
- Public API changes are checked automatically against `origin/main` with Roave Backward Compatibility Check.
- Update README/API/cookbook documentation when public behavior changes.
- Keep coverage at or above the configured 90% project and patch thresholds.
- Source/test changes are mutation-tested with Infection; new behavior should kill relevant mutants rather than only execute lines.
- Use English for source code, comments, commit messages and pull-request descriptions.

## Compatibility

The library parses untrusted ZIP/XML input. Changes touching archive extraction, XML loading, external references, streams or temporary files should include security-oriented regression tests.

## Reporting bugs

Include the package profile, PHP version, a minimal reproducer when possible, and the exact exception or diagnostic code. Do not attach sensitive project files to public issues.
